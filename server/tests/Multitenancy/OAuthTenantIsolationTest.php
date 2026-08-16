<?php
declare(strict_types=1);

use app\common\model\oauth\OAuthIdentity;
use app\common\service\member\MemberTenantContext;
use app\common\service\oauth\OAuthTenantContext;
use app\common\service\oauth\OAuthTenantRepository;
use PeanutAdmin\Kernel\Auth\TenantContext;
use PeanutAdmin\Kernel\Auth\ValidatedTenantSession;
use PeanutAdmin\Kernel\Context\TenantSystemContext;
use PeanutAdmin\Kernel\Persistence\Schema\KernelSchema;

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
        'oauth-session-' . $tenantId . '-' . $memberId,
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
    $name = 'peanut_admin_oauth_fresh_' . strtolower(bin2hex(random_bytes(5)));
    $admin->exec("CREATE DATABASE `{$name}` CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci");
    return $name;
}

function oauthFreshSchema(PDO $pdo, string $serverRoot): void
{
    foreach (KernelSchema::tableNames() as $table) {
        $pdo->exec(KernelSchema::createSql($table));
    }
    $pdo->exec(KernelSchema::addTenantMemberDepartmentForeignKeySql());
    $pdo->exec(<<<'SQL'
INSERT INTO pa_tenant
  (id, code, name, display_name, status, activated_at, created_at, updated_at)
VALUES
  (101, 'default', 'Alpha', 'Alpha', 'active', UTC_TIMESTAMP(3), UTC_TIMESTAMP(3), UTC_TIMESTAMP(3));
SQL);
    $schema = (string)file_get_contents($serverRoot . '/database/init.sql');
    expectOAuthTenant($schema !== '', 'canonical application schema is missing');
    $pdo->exec($schema);
}

$serverRoot = dirname(__DIR__, 2);
$host = getenv('DB_HOST') ?: '127.0.0.1';
$port = (int)(getenv('DB_PORT') ?: 3306);
$password = getenv('MYSQL_ROOT_PASSWORD') ?: 'mt02_root';
$admin = new PDO("mysql:host={$host};port={$port};charset=utf8mb4", 'root', $password, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$database = oauthDatabase($admin);

try {
    $pdo = oauthPdo($host, $port, $password, $database);
    oauthFreshSchema($pdo, $serverRoot);
    $pdo->exec(<<<'SQL'
INSERT INTO pa_tenant
  (id, code, name, display_name, status, activated_at, created_at, updated_at)
VALUES (202, 'beta', 'Beta', 'Beta', 'active', UTC_TIMESTAMP(3), UTC_TIMESTAMP(3), UTC_TIMESTAMP(3));
INSERT INTO pa_member (id, tenant_id, sn, account, nickname, status)
VALUES
  (11, 101, 'M-ALPHA', 'alpha', 'Alpha', 1),
  (22, 202, 'M-BETA', 'beta', 'Beta', 1);
INSERT INTO pa_external_channel_binding
  (id, tenant_id, provider, callback_key, identity_hash, identity_hint, config_json, status)
VALUES
  (202, 202, 'oauth.wechat.oa', SHA2('beta-oauth-callback', 256), SHA2('beta-oauth-identity', 256), 'beta', JSON_OBJECT(), 1);
INSERT INTO pa_oauth_principal (id, tenant_id, provider, union_scope, union_id, member_id)
VALUES (31, 101, 'wechat', 'wechat_default', 'union-shared', 11);
INSERT INTO pa_oauth_identity (id, tenant_id, provider, client_key, subject, principal_id, member_id, terminal)
VALUES (41, 101, 'wechat', 'mnp:app-instance', 'openid-shared', 31, 11, 1);
INSERT INTO pa_oauth_attempt (id, tenant_id, state_hash, scene, return_path, expires_at)
VALUES (51, 101, REPEAT('a', 64), 'oa', '/alpha', 2147483647);
INSERT INTO pa_oauth_completion_ticket
  (id, tenant_id, binding_id, token_hash, member_id, need_profile, need_mobile, expires_at)
SELECT 61, 101, id, REPEAT('b', 64), 11, 1, 0, 2147483647
FROM pa_external_channel_binding
WHERE tenant_id = 101 AND provider = 'oauth.wechat.oa';
SQL);
    putenv('PHP_DB_HOST=' . $host); putenv('PHP_DB_PORT=' . $port); putenv('PHP_DB_NAME=' . $database);
    putenv('PHP_DB_USER=root'); putenv('PHP_DB_PASS=' . $password); putenv('PHP_DB_PREFIX=pa_');
    $app = new think\App(); $app->initialize();

    $alpha = oauthTenantContext(101, 11, 'fresh-oauth-alpha');
    $beta = oauthTenantContext(202, 22, 'fresh-oauth-beta');
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
        'binding_id' => 202, 'need_profile' => 1, 'need_mobile' => 0, 'expires_at' => time() + 600,
    ]);
    expectOAuthTenant((int)OAuthTenantRepository::completionTickets($alpha)->where('token_hash', str_repeat('d', 64))->count() === 0, 'Alpha read Beta completion ticket');

    try {
        OAuthTenantContext::tenantId(new TenantSystemContext(202, 'forged.actor', 'member.oauth-begin', 'forged'));
        throw new RuntimeException('forged OAuth system actor was accepted');
    } catch (Throwable $exception) {
        expectOAuthTenant($exception->getMessage() !== '', 'forged OAuth actor denial lost shape');
    }

    $controller = (string)file_get_contents($serverRoot . '/app/api/controller/OAuthController.php');
    expectOAuthTenant(str_contains($controller, 'ExternalTenantResolver::production()->onlyActiveBinding('), 'OAuth begin does not use the trusted external binding resolver');
} finally {
    $admin->exec("DROP DATABASE IF EXISTS `{$database}`");
}

echo "OAuth tenant isolation passed\n";
