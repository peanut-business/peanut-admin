<?php
declare(strict_types=1);

use app\common\model\oauth\OAuthIdentity;
use app\common\service\member\MemberTenantContext;
use app\common\service\oauth\OAuthTenantContext;
use app\common\service\oauth\OAuthTenantRepository;
use PeanutAdmin\Kernel\Auth\TenantContext;
use PeanutAdmin\Kernel\Auth\ValidatedTenantSession;
use PeanutAdmin\Kernel\Context\TenantSystemContext;

require dirname(__DIR__, 2) . '/vendor/autoload.php';
spl_autoload_register(static function (string $class): void {
    if (!str_starts_with($class, 'app\\')) return;
    $path = dirname(__DIR__, 2) . '/app/' . str_replace('\\', '/', substr($class, 4)) . '.php';
    if (is_file($path)) require_once $path;
}, true, true);

function expectOAuthTenant(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}

function oauthTenantContext(int $tenantId, int $memberId, string $requestId): TenantContext
{
    return TenantContext::fromValidatedSession(new ValidatedTenantSession(
        $memberId,
        '01JMT03OAUTH' . str_pad((string)$memberId, 15, '0', STR_PAD_LEFT),
        $tenantId,
        $memberId + 10000,
        $memberId,
        'member-client',
        new DateTimeImmutable('2031-01-01T00:00:00Z'),
        1,
    ), $requestId);
}

function oauthPdo(string $host, int $port, string $password, string $database): PDO
{
    return new PDO(
        "mysql:host={$host};port={$port};dbname={$database};charset=utf8mb4",
        'root',
        $password,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::MYSQL_ATTR_MULTI_STATEMENTS => true]
    );
}

function oauthDatabase(PDO $admin): string
{
    $name = 'peanut_admin_mt03_oauth_' . strtolower(bin2hex(random_bytes(5)));
    $admin->exec("CREATE DATABASE `{$name}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    return $name;
}

$serverRoot = dirname(__DIR__, 2);
$migration = (string)file_get_contents($serverRoot . '/database/migrations/20260813_oauth_tenant_ownership.sql');
$fixture = (string)file_get_contents($serverRoot . '/tests/fixtures/mt03/oauth-tenant-legacy.sql');
expectOAuthTenant($migration !== '' && $fixture !== '', 'OAuth migration or fixture is missing');

$host = getenv('DB_HOST') ?: '127.0.0.1';
$port = (int)(getenv('DB_PORT') ?: 3306);
$password = getenv('MYSQL_ROOT_PASSWORD') ?: 'mt02_root';
$admin = new PDO("mysql:host={$host};port={$port};charset=utf8mb4", 'root', $password, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$databases = [];

try {
    foreach (['missing_table', 'member_owner_missing', 'invalid_relation', 'ambiguous_attempt'] as $failure) {
        $database = oauthDatabase($admin);
        $databases[] = $database;
        $pdo = oauthPdo($host, $port, $password, $database);
        $pdo->exec($fixture);
        if ($failure === 'missing_table') {
            $pdo->exec('DROP TABLE pa_oauth_completion_ticket');
        } elseif ($failure === 'member_owner_missing') {
            $pdo->exec('ALTER TABLE pa_member MODIFY tenant_id BIGINT UNSIGNED NULL');
        } elseif ($failure === 'invalid_relation') {
            $pdo->exec('UPDATE pa_oauth_identity SET member_id=999 WHERE id=41');
        } else {
            $pdo->exec("UPDATE pa_tenant SET status='active' WHERE id=202");
        }
        try {
            $pdo->exec($migration);
            throw new RuntimeException("{$failure} migration preflight unexpectedly succeeded");
        } catch (PDOException) {
            expectOAuthTenant(
                (int)$pdo->query("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pa_oauth_identity' AND COLUMN_NAME='tenant_id'")->fetchColumn() === 0,
                "{$failure} changed OAuth schema before refusing"
            );
        }
    }

    $database = oauthDatabase($admin);
    $databases[] = $database;
    $pdo = oauthPdo($host, $port, $password, $database);
    $pdo->exec($fixture);
    $pdo->exec($migration);

    foreach (['pa_oauth_principal', 'pa_oauth_identity', 'pa_oauth_attempt', 'pa_oauth_completion_ticket'] as $table) {
        expectOAuthTenant((int)$pdo->query("SELECT tenant_id FROM `{$table}` LIMIT 1")->fetchColumn() === 101, "{$table} legacy row was not backfilled");
        expectOAuthTenant($pdo->query("SELECT IS_NULLABLE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='{$table}' AND COLUMN_NAME='tenant_id'")->fetchColumn() === 'NO', "{$table}.tenant_id is nullable");
    }
    foreach ([
        'pa_oauth_principal' => 'uk_oauth_principal_tenant_union',
        'pa_oauth_identity' => 'uk_oauth_identity_tenant_subject',
        'pa_oauth_attempt' => 'uk_oauth_attempt_tenant_state',
        'pa_oauth_completion_ticket' => 'uk_oauth_ticket_tenant_token',
    ] as $table => $index) {
        $indexes = $pdo->query("SELECT DISTINCT INDEX_NAME FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='{$table}'")->fetchAll(PDO::FETCH_COLUMN);
        expectOAuthTenant(in_array($index, $indexes, true), "{$table}.{$index} is missing");
    }

    $pdo->exec("UPDATE pa_tenant SET status='active' WHERE id=202");
    putenv('PHP_DB_HOST=' . $host); putenv('PHP_DB_PORT=' . $port); putenv('PHP_DB_NAME=' . $database);
    putenv('PHP_DB_USER=root'); putenv('PHP_DB_PASS=' . $password); putenv('PHP_DB_PREFIX=pa_');
    $app = new think\App(); $app->initialize();

    $alpha = oauthTenantContext(101, 11, 'mt03-oauth-alpha');
    $beta = oauthTenantContext(202, 22, 'mt03-oauth-beta');
    $betaPrincipal = OAuthTenantRepository::createPrincipal($beta, [
        'tenant_id' => 101,
        'provider' => 'wechat', 'union_scope' => 'wechat_default',
        'union_id' => 'union-shared', 'member_id' => 22,
    ]);
    expectOAuthTenant((int)$betaPrincipal->tenant_id === 202, 'payload forged OAuth principal Tenant ownership');
    $betaIdentity = OAuthTenantRepository::createIdentity($beta, [
        'tenant_id' => 101,
        'provider' => 'wechat', 'client_key' => 'mnp:app-instance',
        'subject' => 'openid-shared', 'principal_id' => (int)$betaPrincipal->id,
        'member_id' => 22, 'terminal' => 1,
    ]);
    expectOAuthTenant((int)$betaIdentity->tenant_id === 202, 'payload forged OAuth identity Tenant ownership');
    expectOAuthTenant(OAuthTenantRepository::identities($alpha)->where('id', (int)$betaIdentity->id)->findOrEmpty()->isEmpty(), 'Alpha read Beta OAuth identity');
    expectOAuthTenant(OAuthIdentity::subjectForMember($beta, 22, 1) === 'openid-shared', 'payment compatibility lookup lost Beta owned subject');

    $sameStateHash = str_repeat('c', 64);
    OAuthTenantRepository::createAttempt(new TenantSystemContext(101, MemberTenantContext::PUBLIC_AUTH_ACTOR, 'member.oauth-begin', 'alpha-begin'), [
        'state_hash' => $sameStateHash, 'scene' => 'oa', 'return_path' => '/alpha', 'expires_at' => time() + 600,
    ]);
    OAuthTenantRepository::createAttempt(new TenantSystemContext(202, MemberTenantContext::PUBLIC_AUTH_ACTOR, 'member.oauth-begin', 'beta-begin'), [
        'tenant_id' => 101, 'state_hash' => $sameStateHash, 'scene' => 'oa', 'return_path' => '/beta', 'expires_at' => time() + 600,
    ]);
    expectOAuthTenant((int)OAuthTenantRepository::attempts($alpha)->where('state_hash', $sameStateHash)->count() === 1, 'Alpha OAuth state was not isolated');
    expectOAuthTenant((int)OAuthTenantRepository::attempts($beta)->where('state_hash', $sameStateHash)->count() === 1, 'Beta OAuth state was not isolated');

    OAuthTenantRepository::createCompletionTicket($beta, [
        'tenant_id' => 101, 'token_hash' => str_repeat('d', 64), 'member_id' => 22,
        'need_profile' => 1, 'need_mobile' => 0, 'expires_at' => time() + 600,
    ]);
    expectOAuthTenant((int)OAuthTenantRepository::completionTickets($alpha)->where('token_hash', str_repeat('d', 64))->count() === 0, 'Alpha read Beta completion ticket');

    try {
        OAuthTenantContext::tenantId(new TenantSystemContext(202, 'forged.actor', 'member.oauth-begin', 'forged'));
        throw new RuntimeException('forged OAuth system actor was accepted');
    } catch (Throwable $exception) {
        expectOAuthTenant($exception->getMessage() !== '', 'forged OAuth actor denial lost shape');
    }

    expectOAuthTenant(str_contains($migration, 'pa_config OAuth switches and channel credentials remain instance-owned'), 'instance OAuth configuration boundary is missing');
    $controller = (string)file_get_contents($serverRoot . '/app/api/controller/OAuthController.php');
    expectOAuthTenant(str_contains($controller, 'ExternalTenantResolver::production()->onlyActiveBinding('), 'OAuth begin does not use the trusted external binding resolver');
} finally {
    foreach ($databases as $database) $admin->exec("DROP DATABASE IF EXISTS `{$database}`");
}

echo "MT03-OAUTH-TENANT-001 passed\n";
