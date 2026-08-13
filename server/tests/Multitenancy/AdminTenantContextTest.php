<?php
declare(strict_types=1);

use app\adminapi\http\AdminRequest;
use app\adminapi\service\AdminTenantContextResolver;

require dirname(__DIR__, 2) . '/vendor/autoload.php';

function expectAdminTenant(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function rejectAdminTenant(Closure $operation): string
{
    try {
        $operation();
    } catch (DomainException $exception) {
        return $exception->getMessage();
    }
    throw new RuntimeException('tenant context denial was expected');
}

$host = getenv('DB_HOST') ?: (getenv('MYSQL_HOST') ?: '127.0.0.1');
$port = getenv('DB_PORT') ?: (getenv('MYSQL_PORT') ?: '33463');
$password = getenv('MYSQL_ROOT_PASSWORD') ?: 'peanut_admin_root_dev';
$admin = new PDO(
    "mysql:host={$host};port={$port};charset=utf8mb4",
    'root',
    $password,
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES => false]
);
$database = 'pa_mt04_admin_context_' . strtolower(bin2hex(random_bytes(5)));
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
INSERT INTO pa_tenant VALUES (101, 'active'), (202, 'active');
INSERT INTO pa_account VALUES (1001, 'active'), (2002, 'active');
INSERT INTO pa_tenant_member VALUES (501, 101, 1001, 'active', 7), (502, 202, 2002, 'active', 9);
INSERT INTO pa_admin VALUES (1, 101, 0, NULL), (2, 202, 0, NULL);
INSERT INTO pa_legacy_admin_tenant_map VALUES (101, 1, 1001, 501), (202, 2, 2002, 502);
SQL);
    $token = str_repeat('a', 64);
    $insert = $pdo->prepare('INSERT INTO pa_admin_session VALUES (11, 1, ?, ?, ?)');
    $insert->execute([$token, time() - 10, time() + 3600]);

    $resolver = new AdminTenantContextResolver($pdo);
    $context = $resolver->resolve(['id' => 11], 1, $token, 'mt04-admin-alpha');
    expectAdminTenant($context->tenantId === 101, 'trusted session resolved the wrong Tenant');
    expectAdminTenant($context->accountId === 1001 && $context->memberId === 501, 'trusted identity mapping was lost');
    expectAdminTenant($context->authorizationRevision === 7, 'authorization revision was not sourced from TenantMember');
    expectAdminTenant($context->clientKey === 'admin-web' && $context->requestId === 'mt04-admin-alpha', 'context metadata was not preserved');

    $request = new class {
        public function header(string $name, string $default = ''): string
        {
            return $name === 'X-Request-Id' ? 'mt04-request-1' : $default;
        }
        public function post(): array { return ['tenant_id' => 202]; }
    };
    expectAdminTenant(AdminRequest::requestId($request) === 'mt04-request-1', 'valid request id was replaced');
    expectAdminTenant($context->tenantId !== $request->post()['tenant_id'], 'request tenant_id forged TenantContext');

    $denials = [];
    $denials[] = rejectAdminTenant(static fn() => $resolver->resolve(['id' => 11], 2, $token, 'mt04-cross-admin'));
    $denials[] = rejectAdminTenant(static fn() => $resolver->resolve(['id' => 11], 1, str_repeat('b', 64), 'mt04-forged-token'));
    $pdo->exec("UPDATE pa_tenant SET status='suspended' WHERE id=101");
    $denials[] = rejectAdminTenant(static fn() => $resolver->resolve(['id' => 11], 1, $token, 'mt04-suspended'));
    expectAdminTenant(count(array_unique($denials)) === 1 && $denials[0] === 'TENANT_CONTEXT_UNAVAILABLE', 'tenant denials do not share one shape');

    echo "MT04-ADMIN-TENANT-CONTEXT-001 passed\n";
} finally {
    $admin->exec("DROP DATABASE IF EXISTS `{$database}`");
}
