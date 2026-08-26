<?php
declare(strict_types=1);

require dirname(__DIR__, 2) . '/bootstrap/environment.php';

use PeanutAdmin\Kernel\Auth\Persistence\PdoTenantAuthRepository;
use PeanutAdmin\Kernel\Auth\SystemClock;
use PeanutAdmin\Kernel\Auth\TenantAuthService;
use PeanutAdmin\Kernel\Auth\TenantAuthentication;
use PeanutAdmin\Kernel\Auth\TenantSelectionRequired;
use PeanutAdmin\Kernel\Auth\TokenIssuer;
use PeanutAdmin\Kernel\Identity\PasswordHasher;
use PeanutAdmin\Kernel\Migration\ModuleSchema;
use PeanutAdmin\Kernel\Persistence\Pdo\PdoAuditRepository;
use PeanutAdmin\Kernel\Persistence\Pdo\PdoIdentityRepository;
use PeanutAdmin\Kernel\Persistence\Pdo\PdoMembershipRepository;
use PeanutAdmin\Kernel\Persistence\Pdo\PdoPlatformRepository;
use PeanutAdmin\Kernel\Persistence\Pdo\PdoTenantRepository;
use PeanutAdmin\Kernel\Persistence\Pdo\PdoTransactionManager;
use PeanutAdmin\Kernel\Persistence\Schema\KernelSchema;
use PeanutAdmin\Kernel\Platform\Bootstrap\BootstrapService;

require dirname(__DIR__, 2) . '/vendor/autoload.php';
require __DIR__ . '/../Support/IsolatedBackendEnvironment.php';

function tenantHostExpect(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function tenantHostRejects(Closure $operation): void
{
    try {
        $operation();
    } catch (Throwable) {
        return;
    }
    throw new RuntimeException('expected Tenant auth rejection');
}

$host = IsolatedBackendEnvironment::required('DB_HOST');
$port = (int)IsolatedBackendEnvironment::required('DB_PORT');
$user = IsolatedBackendEnvironment::required('DB_USER');
$password = IsolatedBackendEnvironment::required('DB_PASS');
$admin = new PDO(
    "mysql:host={$host};port={$port};charset=utf8mb4",
    $user,
    $password,
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES => false]
);
$database = 'pa_mt04_auth_' . strtolower(bin2hex(random_bytes(6)));
$admin->exec("CREATE DATABASE `{$database}` CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci");

try {
    $pdo = new PDO(
        "mysql:host={$host};port={$port};dbname={$database};charset=utf8mb4",
        $user,
        $password,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, PDO::ATTR_EMULATE_PREPARES => false]
    );
    foreach (KernelSchema::tableNames() as $table) {
        $pdo->exec(KernelSchema::createSql($table));
    }
    $pdo->exec(KernelSchema::addTenantMemberDepartmentForeignKeySql());
    foreach (ModuleSchema::tableNames() as $table) {
        $pdo->exec(ModuleSchema::createSql($table));
    }
    $pdo->exec(<<<'SQL'
ALTER TABLE `pa_login_challenge`
  ADD COLUMN `client_key` VARCHAR(64) NOT NULL AFTER `purpose`,
  ADD CONSTRAINT `chk_login_challenge_client`
    CHECK (REGEXP_LIKE(`client_key`, '^[a-z][a-z0-9-]{0,63}$', 'c'))
SQL);

    $transactions = new PdoTransactionManager($pdo);
    $passwords = new PasswordHasher();
    $bootstrap = new BootstrapService(
        $transactions,
        new PdoIdentityRepository($pdo),
        new PdoTenantRepository($pdo),
        new PdoMembershipRepository($pdo),
        new PdoPlatformRepository($pdo),
        new PdoAuditRepository($pdo),
        $passwords
    );
    $platform = $bootstrap->bootstrapPlatformOwner(
        'multi-owner@example.test',
        'MultiTenantPassword2026',
        'Multi Owner',
        'mt04-platform'
    );
    $alpha = $bootstrap->provisionTenantOwnerCandidate(
        $platform->operatorId, 'alpha-host', 'Alpha Host', 'multi-owner@example.test', null, 'Multi Owner', 'mt04-alpha'
    );
    $bootstrap->activateTenantOwner($platform->operatorId, $alpha->tenantId, $alpha->memberId, 'mt04-alpha-owner');
    $bootstrap->activateTenant($platform->operatorId, $alpha->tenantId, 'mt04-alpha-active');
    $beta = $bootstrap->provisionTenantOwnerCandidate(
        $platform->operatorId, 'beta-host', 'Beta Host', 'multi-owner@example.test', null, 'Multi Owner', 'mt04-beta'
    );
    $bootstrap->activateTenantOwner($platform->operatorId, $beta->tenantId, $beta->memberId, 'mt04-beta-owner');
    $bootstrap->activateTenant($platform->operatorId, $beta->tenantId, 'mt04-beta-active');

    $auth = new TenantAuthService(
        $transactions,
        new PdoTenantAuthRepository($pdo),
        $passwords,
        new SystemClock(),
        new TokenIssuer(),
        str_repeat('t', 32)
    );
    $selection = $auth->login(
        'multi-owner@example.test',
        'MultiTenantPassword2026',
        null,
        '127.0.0.1',
        'MT04 fixture',
        'mt04-login'
    );
    tenantHostExpect($selection instanceof TenantSelectionRequired, 'multi-Tenant login skipped selection');
    tenantHostExpect(count($selection->tenants) === 2, 'selection omitted an available Tenant');
    $alphaAuth = $auth->selectTenant(
        $selection->challenge->expose(),
        $alpha->tenantId,
        '127.0.0.1',
        'MT04 fixture',
        'mt04-select-alpha'
    );
    tenantHostExpect($alphaAuth->context->tenantId === $alpha->tenantId, 'selection established the wrong Tenant');

    $switch = $auth->switchChallenge(
        $alphaAuth->tokens->access->expose(),
        '127.0.0.1',
        'MT04 fixture',
        'mt04-switch-challenge'
    );
    tenantHostExpect(count($switch->tenants) === 1 && $switch->tenants[0]->tenantId === $beta->tenantId, 'switch exposed the current or wrong Tenant');
    $betaAuth = $auth->selectTenant(
        $switch->challenge->expose(),
        $beta->tenantId,
        '127.0.0.1',
        'MT04 fixture',
        'mt04-select-beta'
    );
    tenantHostExpect($betaAuth instanceof TenantAuthentication && $betaAuth->context->tenantId === $beta->tenantId, 'switch did not establish Beta');
    tenantHostRejects(static fn() => $auth->context($alphaAuth->tokens->access->expose(), 'mt04-old-context'));
    tenantHostExpect($auth->context($betaAuth->tokens->access->expose(), 'mt04-beta-context')->tenantId === $beta->tenantId, 'new context was not active');

    $auth->logout($betaAuth->tokens->access->expose(), 'mt04-logout');
    tenantHostRejects(static fn() => $auth->context($betaAuth->tokens->access->expose(), 'mt04-after-logout'));

    $route = (string)file_get_contents(dirname(__DIR__, 2) . '/route/app.php');
    foreach (['login', 'select', 'switch', 'logout'] as $action) {
        tenantHostExpect(str_contains($route, "api/tenant/session/{$action}"), "Tenant session {$action} route is missing");
    }

    echo "MT04-TENANT-SESSION-HOST-001 passed\n";
} finally {
    $admin->exec("DROP DATABASE IF EXISTS `{$database}`");
}
