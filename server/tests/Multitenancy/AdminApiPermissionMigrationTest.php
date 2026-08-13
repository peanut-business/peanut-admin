<?php
declare(strict_types=1);

require dirname(__DIR__, 2) . '/vendor/autoload.php';

function expectPermissionMigration(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$host = getenv('DB_HOST') ?: (getenv('MYSQL_HOST') ?: '127.0.0.1');
$port = (int)(getenv('DB_PORT') ?: (getenv('MYSQL_PORT') ?: 3306));
$user = 'root';
$password = getenv('MYSQL_ROOT_PASSWORD') ?: '';
$database = 'peanut_admin_permission_' . strtolower(bin2hex(random_bytes(6)));
$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => true,
    PDO::MYSQL_ATTR_MULTI_STATEMENTS => true,
];
$admin = new PDO("mysql:host={$host};port={$port};charset=utf8mb4", $user, $password, $options);
$admin->exec("CREATE DATABASE `{$database}` CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci");

try {
    $pdo = new PDO("mysql:host={$host};port={$port};dbname={$database};charset=utf8mb4", $user, $password, $options);
    $pdo->exec(<<<'SQL'
CREATE TABLE pa_system_menu (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  pid INT UNSIGNED NOT NULL DEFAULT 0,
  type CHAR(1) NOT NULL DEFAULT 'A',
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
  PRIMARY KEY (id),
  UNIQUE KEY uk_role_menu_tenant (tenant_id,role_id,menu_id)
) ENGINE=InnoDB;
INSERT INTO pa_system_menu (type,name,perms,paths) VALUES
 ('C','Menu','menu/lists','/system/menu'),('A','Menu Edit','menu/edit',''),
 ('C','Role','role/lists','/system/role'),
 ('C','Admin','admin/lists','/system/admin'),('A','Admin Edit','admin/edit',''),
 ('C','Dept','dept/lists','/system/dept'),('A','Dept Edit','dept/edit',''),
 ('C','Jobs','jobs/lists','/system/jobs'),('A','Jobs Edit','jobs/edit',''),
 ('C','Log','log/lists','/system/log'),('A','Log Export','log/export',''),
 ('C','Article Cate','article.articlecate/lists','/article/cate'),
 ('C','Account Log','finance.account_log/lists','/finance/account-log'),
 ('M','App Setting','','/app-setting'),
 ('C','Recharge','recharge.recharge/lists','/finance/recharge'),
 ('A','Recharge Refund','recharge.recharge/refund',''),
 ('A','Recharge Refund Again','recharge.recharge/refundagain',''),
 ('C','Refund','finance.refund/record','/finance/refund'),
 ('A','Refund Log','finance.refund/log','');
INSERT INTO pa_system_role_menu (tenant_id,role_id,menu_id)
SELECT 101,11,id FROM pa_system_menu;
INSERT INTO pa_system_role_menu (tenant_id,role_id,menu_id)
SELECT 101,22,id FROM pa_system_menu WHERE perms='menu/lists';
SQL);

    $migration = (string)file_get_contents(dirname(__DIR__, 2) . '/database/migrations/20260814_admin_api_default_deny.sql');
    expectPermissionMigration($migration !== '', 'admin permission migration is missing');
    $pdo->exec($migration);

    $registered = (int)$pdo->query(<<<'SQL'
SELECT COUNT(*) FROM pa_system_menu
WHERE LOWER(perms) IN (
 'menu/all','menu/detail','menu/status','role/all','role/detail','admin/detail','admin/status',
 'dept/all','dept/leaderdept','dept/detail','dept/status','jobs/all','jobs/detail','jobs/status',
 'log/export/status','log/export/download','article.articlecate/all',
 'finance/account-log/lists','finance/account-log/change-types','finance.account_log/getumchangetype',
 'setting/transaction/config','setting/transaction/save','finance/recharge/lists',
 'finance/recharge/refund','finance/recharge/refundagain','finance/refund/stat',
 'finance/refund/record','finance/refund/log','finance.refund/stat'
)
SQL)->fetchColumn();
    expectPermissionMigration($registered === 29, 'migration did not create all 29 exact permission nodes');

    $role11 = (int)$pdo->query('SELECT COUNT(*) FROM pa_system_role_menu WHERE tenant_id=101 AND role_id=11')->fetchColumn();
    expectPermissionMigration($role11 === 48, 'existing broad role did not inherit all 29 legitimate routes');
    $role22Permissions = $pdo->query(<<<'SQL'
SELECT LOWER(m.perms)
FROM pa_system_role_menu rm JOIN pa_system_menu m ON m.id=rm.menu_id
WHERE rm.tenant_id=101 AND rm.role_id=22
ORDER BY LOWER(m.perms)
SQL)->fetchAll(PDO::FETCH_COLUMN);
    expectPermissionMigration(
        $role22Permissions === ['menu/all', 'menu/detail', 'menu/lists'],
        'narrow role received an unrelated permission during migration'
    );

    $pdo->exec($migration);
    expectPermissionMigration(
        (int)$pdo->query('SELECT COUNT(*) FROM pa_system_menu')->fetchColumn() === 48,
        'permission migration is not idempotent'
    );
    expectPermissionMigration(
        (int)$pdo->query('SELECT COUNT(*) FROM pa_system_role_menu')->fetchColumn() === 51,
        'role grant migration is not idempotent'
    );

    echo "ADMIN-API-PERMISSION-MIGRATION-001 passed\n";
} finally {
    $admin->exec("DROP DATABASE IF EXISTS `{$database}`");
}
