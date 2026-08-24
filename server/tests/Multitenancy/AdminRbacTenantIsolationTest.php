<?php
declare(strict_types=1);

use app\common\service\authorization\AdminAuthorizationService;
use PeanutAdmin\Kernel\Auth\TenantContext;
use PeanutAdmin\Kernel\Auth\ValidatedTenantSession;
use PeanutAdmin\Kernel\Persistence\Schema\KernelSchema;

require dirname(__DIR__, 2) . '/vendor/autoload.php';

function expectRbacTenant(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function rbacTenantContext(int $tenantId, int $accountId, int $memberId, string $requestId): TenantContext
{
    return TenantContext::fromValidatedSession(new ValidatedTenantSession(
        $memberId,
        'rbac-session-' . $tenantId . '-' . $memberId,
        $tenantId,
        $accountId,
        $memberId,
        'admin-web',
        new DateTimeImmutable('2031-01-01T00:00:00Z'),
        1,
    ), $requestId);
}

function createNativeRbacSchema(PDO $pdo, string $serverRoot): void
{
    foreach (KernelSchema::tableNames() as $table) {
        $pdo->exec(KernelSchema::createSql($table));
    }
    $pdo->exec(KernelSchema::addTenantMemberDepartmentForeignKeySql());
    $pdo->exec(<<<'SQL'
INSERT INTO pa_tenant
  (id, code, name, display_name, status, activated_at, created_at, updated_at)
VALUES
  (1, 'default', 'Default', 'Default', 'active', UTC_TIMESTAMP(3), UTC_TIMESTAMP(3), UTC_TIMESTAMP(3));
SQL);
    $schema = (string)file_get_contents($serverRoot . '/database/init.sql');
    expectRbacTenant($schema !== '', 'canonical application schema is missing');
    $pdo->exec($schema);
}

$serverRoot = dirname(__DIR__, 2);
$host = trim((string)getenv('DB_HOST'));
$port = (int)(getenv('DB_PORT') ?: 0);
$user = trim((string)(getenv('DB_USER') ?: getenv('MYSQL_USER')));
$password = (string)(getenv('DB_PASS') ?: getenv('MYSQL_PASSWORD'));
$database = trim((string)getenv('DB_NAME'));
if ($host === '' || $port < 1 || $user === '' || $password === '' || $database === '') {
    throw new RuntimeException('registered database environment is required');
}

$admin = new PDO(
    "mysql:host={$host};port={$port};charset=utf8mb4",
    $user,
    $password,
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);
$admin->exec("DROP DATABASE IF EXISTS `{$database}`");
$admin->exec("CREATE DATABASE `{$database}` CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci");

try {
    $pdo = new PDO(
        "mysql:host={$host};port={$port};dbname={$database};charset=utf8mb4",
        $user,
        $password,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::MYSQL_ATTR_MULTI_STATEMENTS => true,
        ]
    );
    createNativeRbacSchema($pdo, $serverRoot);
    $now = '2030-01-01 00:00:00.000';
    $pdo->exec(<<<SQL
INSERT INTO pa_tenant
  (id, code, name, display_name, status, activated_at, created_at, updated_at)
VALUES
  (101, 'alpha', 'Alpha', 'Alpha', 'active', '{$now}', '{$now}', '{$now}'),
  (202, 'beta', 'Beta', 'Beta', 'active', '{$now}', '{$now}', '{$now}');
INSERT INTO pa_account (id, display_name, status, created_at, updated_at) VALUES
  (1001, 'Alpha member', 'active', '{$now}', '{$now}'),
  (1002, 'Beta member', 'active', '{$now}', '{$now}'),
  (1003, 'Alpha root', 'active', '{$now}', '{$now}'),
  (1004, 'Beta root', 'active', '{$now}', '{$now}');
INSERT INTO pa_credential
  (account_id, kind, identifier_type, identifier_normalized, secret_hash, status, verified_at, secret_changed_at, created_at, updated_at)
VALUES
  (1001, 'email_password', 'email', 'alpha@example.test', REPEAT('a', 64), 'active', '{$now}', '{$now}', '{$now}', '{$now}'),
  (1002, 'email_password', 'email', 'beta@example.test', REPEAT('b', 64), 'active', '{$now}', '{$now}', '{$now}', '{$now}'),
  (1003, 'email_password', 'email', 'alpha-root@example.test', REPEAT('c', 64), 'active', '{$now}', '{$now}', '{$now}', '{$now}'),
  (1004, 'email_password', 'email', 'beta-root@example.test', REPEAT('d', 64), 'active', '{$now}', '{$now}', '{$now}', '{$now}');
INSERT INTO pa_tenant_member
  (id, tenant_id, account_id, display_name, status, authorization_revision, joined_at, created_at, updated_at)
VALUES
  (501, 101, 1001, 'Alpha member', 'active', 1, '{$now}', '{$now}', '{$now}'),
  (502, 202, 1002, 'Beta member', 'active', 1, '{$now}', '{$now}', '{$now}'),
  (503, 101, 1003, 'Alpha root', 'active', 1, '{$now}', '{$now}', '{$now}'),
  (504, 202, 1004, 'Beta root', 'active', 1, '{$now}', '{$now}', '{$now}');
INSERT INTO pa_module_installation
  (module_key, installed_version, manifest_schema_version, manifest_digest, status, installed_at, activated_at, created_at, updated_at)
VALUES
  ('peanut.admin', '2.0.0', 1, REPEAT('d', 64), 'active', '{$now}', '{$now}', '{$now}', '{$now}');
INSERT INTO pa_tenant_module
  (tenant_id, module_key, status, source, enabled_at, created_at, updated_at)
VALUES
  (101, 'peanut.admin', 'enabled', 'manual', '{$now}', '{$now}', '{$now}');
INSERT INTO pa_permission
  (`key`, module_key, type, name, description, risk_level, status, manifest_version, created_at, updated_at, retired_at)
VALUES
  ('log/export', 'peanut.admin', 'api', 'Export operation logs', NULL, 'normal', 'active', 'fresh-schema-v1', '{$now}', '{$now}', NULL)
ON DUPLICATE KEY UPDATE status = 'active', updated_at = VALUES(updated_at), retired_at = NULL;
INSERT INTO pa_role
  (id, tenant_id, `key`, name, is_builtin, status, authorization_revision, created_at, updated_at)
VALUES
  (11, 101, 'alpha.export', 'Alpha export', 0, 'active', 1, '{$now}', '{$now}'),
  (22, 202, 'beta.export', 'Beta export', 0, 'active', 1, '{$now}', '{$now}'),
  (33, 101, 'core.tenant-owner', 'Alpha owner', 1, 'active', 1, '{$now}', '{$now}'),
  (44, 202, 'core.tenant-owner', 'Beta owner', 1, 'active', 1, '{$now}', '{$now}');
INSERT INTO pa_member_role (tenant_id, tenant_member_id, role_id, assigned_at) VALUES
  (101, 501, 11, '{$now}'),
  (202, 502, 22, '{$now}'),
  (101, 503, 33, '{$now}'),
  (202, 504, 44, '{$now}');
SQL);
    $permissionId = (int)$pdo->query("SELECT id FROM pa_permission WHERE `key` = 'log/export'")->fetchColumn();
    expectRbacTenant($permissionId > 0, 'canonical export permission is missing');
    $pdo->prepare(
        'INSERT INTO pa_role_permission (tenant_id, role_id, permission_id, granted_at) VALUES (?, ?, ?, ?)'
    )->execute([101, 11, $permissionId, $now]);

    putenv('PHP_DB_HOST=' . $host);
    putenv('PHP_DB_PORT=' . $port);
    putenv('PHP_DB_NAME=' . $database);
    putenv('PHP_DB_USER=' . $user);
    putenv('PHP_DB_PASS=' . $password);
    putenv('PHP_DB_PREFIX=pa_');
    $app = new think\App($serverRoot);
    $app->initialize();

    $authorization = new AdminAuthorizationService($pdo);
    $alphaContext = rbacTenantContext(101, 1001, 501, 'native-rbac-alpha-' . $database);
    $betaContext = rbacTenantContext(202, 1002, 502, 'native-rbac-beta-' . $database);
    $alphaRootContext = rbacTenantContext(101, 1003, 503, 'native-rbac-alpha-root-' . $database);
    $betaRootContext = rbacTenantContext(202, 1004, 504, 'native-rbac-beta-root-' . $database);
    $alpha = $authorization->principal($alphaContext);
    $beta = $authorization->principal($betaContext);
    $alphaRoot = $authorization->principal($alphaRootContext);
    $betaRoot = $authorization->principal($betaRootContext);

    expectRbacTenant(
        $authorization->decide($alphaContext, $alpha, 'log/export')->allowed,
        'same-Tenant RBAC permission was denied'
    );
    expectRbacTenant(
        !$authorization->decide($betaContext, $alpha, 'log/export')->allowed,
        'mismatched TenantContext accepted an Alpha principal'
    );
    expectRbacTenant(
        !$authorization->decide(null, $alpha, 'log/export')->allowed,
        'missing TenantContext did not fail closed'
    );
    expectRbacTenant(
        $authorization->decide($alphaRootContext, $alphaRoot, 'log/export')->allowed,
        'root did not bypass role grant for an active Module permission'
    );
    expectRbacTenant(
        !$authorization->decide($alphaRootContext, $alphaRoot, 'unregistered/read')->allowed,
        'root bypassed route registration'
    );
    expectRbacTenant(
        !$authorization->decide($betaRootContext, $betaRoot, 'log/export')->allowed,
        'root bypassed disabled Tenant Module'
    );

    $data = $authorization->accessData($alphaContext, $alpha);
    expectRbacTenant($data->menu !== [], 'enabled Tenant Module menu projection was empty');
    expectRbacTenant(
        $authorization->accessData($betaContext, $beta)->menu === [],
        'disabled Tenant Module menu leaked into Beta'
    );

    $pdo->exec('DELETE FROM pa_role_permission WHERE tenant_id = 101 AND role_id = 11');
    expectRbacTenant(
        !$authorization->decide($alphaContext, $alpha, 'log/export')->allowed,
        'revoked RBAC permission still authorized'
    );
    $pdo->prepare(
        'INSERT INTO pa_role_permission (tenant_id, role_id, permission_id, granted_at) VALUES (?, ?, ?, ?)'
    )->execute([101, 11, $permissionId, $now]);
    $pdo->exec("UPDATE pa_tenant_module SET status = 'disabled', disabled_at = UTC_TIMESTAMP(3) WHERE tenant_id = 101");
    expectRbacTenant(
        !$authorization->decide($alphaRootContext, $alphaRoot, 'log/export')->allowed,
        'root bypassed Module revocation'
    );
} finally {
    $admin->exec("DROP DATABASE IF EXISTS `{$database}`");
}

echo "NATIVE-ADMIN-RBAC-TENANT-ISOLATION-001 passed\n";
