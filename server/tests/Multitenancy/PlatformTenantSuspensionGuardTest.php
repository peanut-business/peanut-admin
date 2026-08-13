<?php
declare(strict_types=1);

use app\adminapi\service\AdminLoginTenantGuard;
use app\adminapi\service\AdminTenantContextResolver;

require dirname(__DIR__, 2) . '/vendor/autoload.php';

function suspensionExpect(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function suspensionRejects(Closure $operation): void
{
    try {
        $operation();
    } catch (DomainException $exception) {
        suspensionExpect(
            in_array($exception->getMessage(), ['TENANT_UNAVAILABLE', 'TENANT_CONTEXT_UNAVAILABLE'], true),
            'suspension denial changed shape'
        );
        return;
    }
    throw new RuntimeException('suspended Tenant unexpectedly passed a guard');
}

$host = getenv('DB_HOST') ?: getenv('MYSQL_HOST') ?: '127.0.0.1';
$port = (int)(getenv('DB_PORT') ?: getenv('MYSQL_PORT') ?: 3306);
$password = getenv('MYSQL_ROOT_PASSWORD') ?: getenv('DB_PASS') ?: 'peanut_admin_root_dev';
$admin = new PDO(
    "mysql:host={$host};port={$port};charset=utf8mb4",
    'root',
    $password,
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES => false]
);
$database = 'pa_pm01_suspend_' . strtolower(bin2hex(random_bytes(6)));
$admin->exec("CREATE DATABASE `{$database}` CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci");

try {
    $pdo = new PDO(
        "mysql:host={$host};port={$port};dbname={$database};charset=utf8mb4",
        'root',
        $password,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES => false]
    );
    $pdo->exec(<<<'SQL'
CREATE TABLE pa_admin (id INT UNSIGNED PRIMARY KEY, tenant_id BIGINT UNSIGNED NOT NULL, disable TINYINT NOT NULL, delete_time INT NULL);
CREATE TABLE pa_admin_session (id INT UNSIGNED PRIMARY KEY, admin_id INT UNSIGNED NOT NULL, token CHAR(64) NOT NULL, update_time INT UNSIGNED NOT NULL, expire_time INT UNSIGNED NOT NULL);
CREATE TABLE pa_tenant (id BIGINT UNSIGNED PRIMARY KEY, status VARCHAR(32) NOT NULL);
CREATE TABLE pa_account (id BIGINT UNSIGNED PRIMARY KEY, status VARCHAR(32) NOT NULL);
CREATE TABLE pa_tenant_member (id BIGINT UNSIGNED PRIMARY KEY, tenant_id BIGINT UNSIGNED NOT NULL, account_id BIGINT UNSIGNED NOT NULL, status VARCHAR(32) NOT NULL, authorization_revision BIGINT UNSIGNED NOT NULL);
CREATE TABLE pa_legacy_admin_tenant_map (tenant_id BIGINT UNSIGNED NOT NULL, legacy_admin_id INT UNSIGNED NOT NULL, account_id BIGINT UNSIGNED NOT NULL, tenant_member_id BIGINT UNSIGNED NOT NULL, PRIMARY KEY (tenant_id, legacy_admin_id));
INSERT INTO pa_tenant VALUES (101, 'active');
INSERT INTO pa_account VALUES (1001, 'active');
INSERT INTO pa_tenant_member VALUES (501, 101, 1001, 'active', 7);
INSERT INTO pa_admin VALUES (1, 101, 0, NULL);
INSERT INTO pa_legacy_admin_tenant_map VALUES (101, 1, 1001, 501);
INSERT INTO pa_admin_session VALUES (11, 1, 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa', UNIX_TIMESTAMP(), UNIX_TIMESTAMP() + 3600);
SQL);

    $login = new AdminLoginTenantGuard($pdo);
    $resolver = new AdminTenantContextResolver($pdo);
    $token = str_repeat('a', 64);
    $login->assertAllowed(1);
    $resolver->resolve(['id' => 11], 1, $token, 'pm01-active-business-write');

    $beforeSessions = (int)$pdo->query('SELECT COUNT(*) FROM pa_admin_session')->fetchColumn();
    $pdo->exec("UPDATE pa_tenant SET status='suspended' WHERE id=101");
    suspensionRejects(static fn() => $login->assertAllowed(1));
    suspensionExpect(
        (int)$pdo->query('SELECT COUNT(*) FROM pa_admin_session')->fetchColumn() === $beforeSessions,
        'suspended Tenant login guard created a session'
    );

    $writeReached = false;
    try {
        $resolver->resolve(['id' => 11], 1, $token, 'pm01-suspended-business-write');
        $writeReached = true;
    } catch (DomainException $exception) {
        suspensionExpect($exception->getMessage() === 'TENANT_CONTEXT_UNAVAILABLE', 'old-session denial changed shape');
    }
    suspensionExpect(!$writeReached, 'suspended Tenant old session reached business-write execution');

    $route = (string)file_get_contents(dirname(__DIR__, 2) . '/route/app.php');
    suspensionExpect(
        str_contains($route, "Route::post('api/platform/tenants/suspend'")
            && substr_count($route, "PlatformPermissionMiddleware::class, 'platform.tenant.lifecycle'") >= 2,
        'Tenant suspension route lost its lifecycle permission'
    );

    echo "PM01-PLATFORM-TENANT-SUSPENSION-GUARD-001 passed\n";
} finally {
    $admin->exec("DROP DATABASE IF EXISTS `{$database}`");
}
