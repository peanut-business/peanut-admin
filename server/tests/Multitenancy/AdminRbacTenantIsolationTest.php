<?php
declare(strict_types=1);

use app\adminapi\service\AdminPermissionService;
use PeanutAdmin\Kernel\Auth\TenantContext;
use PeanutAdmin\Kernel\Auth\ValidatedTenantSession;

require dirname(__DIR__, 2) . '/vendor/autoload.php';

function expectRbacTenant(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function rbacTenantContext(int $tenantId, int $memberId, string $requestId): TenantContext
{
    return TenantContext::fromValidatedSession(new ValidatedTenantSession(
        $memberId,
        'rbac-session-' . $tenantId . '-' . $memberId,
        $tenantId,
        $tenantId + 1000,
        $memberId,
        'admin-web',
        new DateTimeImmutable('2031-01-01T00:00:00Z'),
        1,
    ), $requestId);
}

function createRbacTenantSchema(PDO $pdo): void
{
    $pdo->exec(<<<'SQL'
CREATE TABLE pa_admin (
  id INT UNSIGNED NOT NULL,
  tenant_id BIGINT UNSIGNED NOT NULL,
  username VARCHAR(50) NOT NULL DEFAULT '',
  nickname VARCHAR(50) NOT NULL DEFAULT '',
  password VARCHAR(64) NOT NULL DEFAULT '',
  salt VARCHAR(16) NOT NULL DEFAULT '',
  avatar VARCHAR(255) NOT NULL DEFAULT '',
  root TINYINT UNSIGNED NOT NULL DEFAULT 0,
  disable TINYINT UNSIGNED NOT NULL DEFAULT 0,
  delete_time INT UNSIGNED NULL,
  PRIMARY KEY (id), UNIQUE KEY uk_admin_tenant_id (tenant_id, id)
) ENGINE=InnoDB;
CREATE TABLE pa_system_role (
  id INT UNSIGNED NOT NULL,
  tenant_id BIGINT UNSIGNED NOT NULL,
  name VARCHAR(50) NOT NULL DEFAULT '',
  `desc` VARCHAR(255) NOT NULL DEFAULT '',
  sort SMALLINT NOT NULL DEFAULT 0,
  delete_time INT UNSIGNED NULL,
  PRIMARY KEY (id), UNIQUE KEY uk_system_role_tenant_id (tenant_id, id)
) ENGINE=InnoDB;
CREATE TABLE pa_admin_role (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  tenant_id BIGINT UNSIGNED NOT NULL,
  admin_id INT UNSIGNED NOT NULL,
  role_id INT UNSIGNED NOT NULL,
  PRIMARY KEY (id), UNIQUE KEY uk_admin_role_tenant (tenant_id, admin_id, role_id)
) ENGINE=InnoDB;
CREATE TABLE pa_system_menu (
  id INT UNSIGNED NOT NULL,
  pid INT UNSIGNED NOT NULL DEFAULT 0,
  type CHAR(1) NOT NULL,
  name VARCHAR(50) NOT NULL DEFAULT '',
  icon VARCHAR(100) NOT NULL DEFAULT '',
  sort SMALLINT NOT NULL DEFAULT 0,
  perms VARCHAR(100) NOT NULL DEFAULT '',
  paths VARCHAR(200) NOT NULL DEFAULT '',
  component VARCHAR(200) NOT NULL DEFAULT '',
  is_cache TINYINT UNSIGNED NOT NULL DEFAULT 0,
  is_show TINYINT UNSIGNED NOT NULL DEFAULT 1,
  is_disable TINYINT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (id)
) ENGINE=InnoDB;
CREATE TABLE pa_system_role_menu (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  tenant_id BIGINT UNSIGNED NOT NULL,
  role_id INT UNSIGNED NOT NULL,
  menu_id INT UNSIGNED NOT NULL,
  PRIMARY KEY (id), UNIQUE KEY uk_role_menu_tenant (tenant_id, role_id, menu_id)
) ENGINE=InnoDB;
SQL);
}

$serverRoot = dirname(__DIR__, 2);
$host = getenv('DB_HOST') ?: (getenv('MYSQL_HOST') ?: '127.0.0.1');
$port = (int)(getenv('DB_PORT') ?: (getenv('MYSQL_PORT') ?: 3306));
$password = getenv('MYSQL_ROOT_PASSWORD') ?: 'peanut_admin_root_dev';
$runId = strtolower(bin2hex(random_bytes(6)));
$database = 'peanut_admin_mt03_rbac_' . $runId;
$admin = new PDO(
    "mysql:host={$host};port={$port};charset=utf8mb4",
    'root',
    $password,
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::MYSQL_ATTR_MULTI_STATEMENTS => true]
);
$admin->exec("CREATE DATABASE `{$database}` CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci");

try {
    $pdo = new PDO(
        "mysql:host={$host};port={$port};dbname={$database};charset=utf8mb4",
        'root',
        $password,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::MYSQL_ATTR_MULTI_STATEMENTS => true,
        ]
    );
    createRbacTenantSchema($pdo);
    $pdo->exec(<<<'SQL'
INSERT INTO pa_admin (id,tenant_id,username,nickname) VALUES
  (1,101,'alpha-admin','Alpha Admin'),
  (2,202,'beta-admin','Beta Admin');
INSERT INTO pa_system_role (id,tenant_id,name) VALUES
  (11,101,'Alpha Role'),
  (22,202,'Beta Role');
INSERT INTO pa_system_menu (id,pid,type,name,perms,paths) VALUES
  (1011,0,'C','Alpha Page','alpha/page','/alpha'),
  (1012,1011,'A','Alpha Read','alpha/read',''),
  (2021,0,'C','Beta Page','beta/page','/beta'),
  (2022,2021,'A','Beta Read','beta/read','');
-- Both pivot rows are intentionally polluted: they claim Beta ownership for Alpha admin/role IDs.
INSERT INTO pa_admin_role (tenant_id,admin_id,role_id) VALUES (202,1,22);
INSERT INTO pa_system_role_menu (tenant_id,role_id,menu_id) VALUES (202,11,2021),(202,11,2022),(202,22,2021),(202,22,2022);
SQL);

    putenv('PHP_DB_HOST=' . $host);
    putenv('PHP_DB_PORT=' . $port);
    putenv('PHP_DB_NAME=' . $database);
    putenv('PHP_DB_USER=root');
    putenv('PHP_DB_PASS=' . $password);
    putenv('PHP_DB_PREFIX=pa_');
    $app = new think\App($serverRoot);
    $app->initialize();

    $alphaContext = rbacTenantContext(101, 501, 'mt03-rbac-alpha-' . $runId);
    $betaContext = rbacTenantContext(202, 502, 'mt03-rbac-beta-' . $runId);
    $forgedAdmin = [
        'id' => 1,
        'tenant_id' => 101,
        'root' => 0,
        'roles' => [['id' => 22]],
    ];
    expectRbacTenant(
        AdminPermissionService::canAccess($alphaContext, $forgedAdmin, 'beta/read') === false,
        'cross-Tenant admin_role granted a registered Beta permission'
    );
    expectRbacTenant(
        AdminPermissionService::menusForAdmin($alphaContext, $forgedAdmin) === [],
        'cross-Tenant admin_role granted a Beta menu'
    );
    expectRbacTenant(
        AdminPermissionService::canAccess($betaContext, $forgedAdmin, 'beta/read') === false,
        'mismatched TenantContext was accepted for an Alpha admin'
    );
    expectRbacTenant(
        AdminPermissionService::canAccess(null, $forgedAdmin, 'beta/read') === false,
        'missing TenantContext did not fail closed'
    );

    $pdo->exec('INSERT INTO pa_admin_role (tenant_id,admin_id,role_id) VALUES (101,1,11)');
    expectRbacTenant(
        AdminPermissionService::canAccess($alphaContext, $forgedAdmin, 'beta/read') === false,
        'cross-Tenant system_role_menu granted a registered Beta permission'
    );
    expectRbacTenant(
        AdminPermissionService::menusForAdmin($alphaContext, $forgedAdmin) === [],
        'cross-Tenant system_role_menu granted a Beta menu'
    );

    $pdo->exec('INSERT INTO pa_system_role_menu (tenant_id,role_id,menu_id) VALUES (101,11,1011),(101,11,1012)');
    expectRbacTenant(
        AdminPermissionService::canAccess($alphaContext, $forgedAdmin, 'alpha/read'),
        'same-Tenant role permission was denied'
    );
    $menus = AdminPermissionService::menusForAdmin($alphaContext, $forgedAdmin);
    expectRbacTenant(
        count($menus) === 1 && (int)$menus[0]['id'] === 1011,
        'same-Tenant role menu was not returned'
    );
} finally {
    $admin->exec("DROP DATABASE IF EXISTS `{$database}`");
}

echo "MT03-ADMIN-RBAC-TENANT-ISOLATION-001 passed\n";
