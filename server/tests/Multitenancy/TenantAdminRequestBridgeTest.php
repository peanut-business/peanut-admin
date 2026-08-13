<?php
declare(strict_types=1);

use app\adminapi\service\TenantAdminPrincipalResolver;
use PeanutAdmin\Kernel\Auth\TenantContext;
use PeanutAdmin\Kernel\Auth\ValidatedTenantSession;

require dirname(__DIR__, 2) . '/vendor/autoload.php';

function tenantAdminBridgeExpect(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}

function tenantAdminBridgeRejects(Closure $operation): void
{
    try { $operation(); } catch (DomainException) { return; }
    throw new RuntimeException('expected Tenant Admin principal rejection');
}

function tenantAdminBridgeContext(int $tenantId, int $accountId, int $memberId): TenantContext
{
    return TenantContext::fromValidatedSession(new ValidatedTenantSession(
        1, 'mt04-bridge-session', $tenantId, $accountId, $memberId, 'admin-web',
        new DateTimeImmutable('2026-08-13T00:00:00Z'), 1
    ), 'mt04-admin-request-bridge');
}

$host = getenv('DB_HOST') ?: getenv('MYSQL_HOST') ?: '127.0.0.1';
$port = (int)(getenv('DB_PORT') ?: getenv('MYSQL_PORT') ?: 3306);
$password = getenv('MYSQL_ROOT_PASSWORD') ?: getenv('DB_PASS') ?: 'peanut_admin_root_dev';
$admin = new PDO(
    "mysql:host={$host};port={$port};charset=utf8mb4", 'root', $password,
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES => false]
);
$database = 'pa_mt04_admin_bridge_' . strtolower(bin2hex(random_bytes(6)));
$admin->exec("CREATE DATABASE `{$database}` CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci");

try {
    $pdo = new PDO(
        "mysql:host={$host};port={$port};dbname={$database};charset=utf8mb4", 'root', $password,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES => false]
    );
    $pdo->exec(<<<'SQL'
CREATE TABLE pa_admin (
  id INT UNSIGNED NOT NULL PRIMARY KEY, tenant_id BIGINT UNSIGNED NOT NULL,
  disable TINYINT NOT NULL DEFAULT 0, delete_time INT UNSIGNED NULL
) ENGINE=InnoDB;
CREATE TABLE pa_legacy_admin_tenant_map (
  tenant_id BIGINT UNSIGNED NOT NULL, legacy_admin_id INT UNSIGNED NOT NULL,
  account_id BIGINT UNSIGNED NOT NULL, tenant_member_id BIGINT UNSIGNED NOT NULL,
  PRIMARY KEY (tenant_id, legacy_admin_id), UNIQUE KEY uk_account (account_id),
  UNIQUE KEY uk_member (tenant_id, tenant_member_id)
) ENGINE=InnoDB;
INSERT INTO pa_admin VALUES (11, 101, 0, NULL), (22, 202, 0, NULL), (33, 303, 1, NULL);
INSERT INTO pa_legacy_admin_tenant_map VALUES
  (101, 11, 1001, 501), (202, 22, 2002, 502), (303, 33, 3003, 503);
SQL);

    $resolver = new TenantAdminPrincipalResolver($pdo);
    tenantAdminBridgeExpect($resolver->resolve(tenantAdminBridgeContext(101, 1001, 501)) === 11, 'validated Tenant identity resolved the wrong Admin');
    tenantAdminBridgeExpect($resolver->resolve(tenantAdminBridgeContext(202, 2002, 502)) === 22, 'second Tenant identity resolved the wrong Admin');
    tenantAdminBridgeRejects(static fn() => $resolver->resolve(tenantAdminBridgeContext(202, 1001, 501)));
    tenantAdminBridgeRejects(static fn() => $resolver->resolve(tenantAdminBridgeContext(101, 2002, 502)));
    tenantAdminBridgeRejects(static fn() => $resolver->resolve(tenantAdminBridgeContext(303, 3003, 503)));

    $middleware = (string)file_get_contents(dirname(__DIR__, 2) . '/app/adminapi/http/middleware/LoginMiddleware.php');
    tenantAdminBridgeExpect(str_contains($middleware, "str_starts_with(\$token, 'pa_tat_')"), 'Login middleware does not route Core Tenant access tokens');
    tenantAdminBridgeExpect(str_contains($middleware, 'TenantAuthRuntimeFactory::service()->context'), 'Login middleware bypasses Core Tenant session validation');
    echo "MT04-TENANT-ADMIN-REQUEST-BRIDGE-001 passed\n";
} finally {
    $admin->exec("DROP DATABASE IF EXISTS `{$database}`");
}
