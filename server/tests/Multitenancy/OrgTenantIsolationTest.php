<?php
declare(strict_types=1);

use app\adminapi\logic\auth\AdminLogic;
use app\adminapi\logic\auth\RoleLogic;
use app\adminapi\logic\dept\DeptLogic;
use app\adminapi\logic\dept\JobsLogic;
use app\common\model\auth\Admin;
use app\common\model\auth\AdminRole;
use app\common\model\auth\SystemRole;
use app\common\model\dept\Dept;
use app\common\model\dept\Jobs;
use app\common\service\org\OrgTenantContext;
use PeanutAdmin\Kernel\Auth\TenantContext;
use PeanutAdmin\Kernel\Auth\ValidatedTenantSession;

require dirname(__DIR__, 2) . '/vendor/autoload.php';

function expectOrgTenant(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function orgTenantContext(int $tenantId, int $memberId, string $requestId): TenantContext
{
    return TenantContext::fromValidatedSession(new ValidatedTenantSession(
        $memberId,
        'org-session-' . $tenantId . '-' . $memberId,
        $tenantId,
        $tenantId + 1000,
        $memberId,
        'admin-web',
        new DateTimeImmutable('2031-01-01T00:00:00Z'),
        1,
    ), $requestId);
}

function createOrgTenantSchema(PDO $pdo): void
{
    $pdo->exec(<<<'SQL'
CREATE TABLE pa_tenant (id BIGINT UNSIGNED NOT NULL, status VARCHAR(32) NOT NULL, PRIMARY KEY (id)) ENGINE=InnoDB;
CREATE TABLE pa_admin (
 id INT UNSIGNED NOT NULL AUTO_INCREMENT, tenant_id BIGINT UNSIGNED NOT NULL,
 username VARCHAR(50) NOT NULL DEFAULT '', nickname VARCHAR(50) NOT NULL DEFAULT '',
 active_username VARCHAR(50) GENERATED ALWAYS AS (IF(delete_time IS NULL, username, NULL)) STORED,
 active_nickname VARCHAR(50) GENERATED ALWAYS AS (IF(delete_time IS NULL, nickname, NULL)) STORED,
 password VARCHAR(64) NOT NULL DEFAULT '', salt VARCHAR(16) NOT NULL DEFAULT '', avatar VARCHAR(255) NOT NULL DEFAULT '',
 root TINYINT NOT NULL DEFAULT 0, disable TINYINT NOT NULL DEFAULT 0, login_time INT UNSIGNED NOT NULL DEFAULT 0,
 login_ip VARCHAR(45) NOT NULL DEFAULT '', multipoint_login TINYINT NOT NULL DEFAULT 1,
 create_time INT UNSIGNED NOT NULL DEFAULT 0, update_time INT UNSIGNED NOT NULL DEFAULT 0, delete_time INT UNSIGNED NULL,
 PRIMARY KEY (id), UNIQUE KEY uk_admin_tenant_id (tenant_id,id),
 UNIQUE KEY uk_admin_tenant_active_username (tenant_id,active_username),
 UNIQUE KEY uk_admin_tenant_active_nickname (tenant_id,active_nickname),
 CONSTRAINT fk_admin_tenant FOREIGN KEY (tenant_id) REFERENCES pa_tenant(id)
) ENGINE=InnoDB;
CREATE TABLE pa_system_role (
 id INT UNSIGNED NOT NULL AUTO_INCREMENT, tenant_id BIGINT UNSIGNED NOT NULL, name VARCHAR(50) NOT NULL DEFAULT '',
 active_name VARCHAR(50) GENERATED ALWAYS AS (IF(delete_time IS NULL, name, NULL)) STORED,
 `desc` VARCHAR(255) NOT NULL DEFAULT '', sort SMALLINT NOT NULL DEFAULT 0,
 create_time INT UNSIGNED NOT NULL DEFAULT 0, update_time INT UNSIGNED NOT NULL DEFAULT 0, delete_time INT UNSIGNED NULL,
 PRIMARY KEY (id), UNIQUE KEY uk_system_role_tenant_id (tenant_id,id),
 UNIQUE KEY uk_system_role_tenant_active_name (tenant_id,active_name),
 CONSTRAINT fk_system_role_tenant FOREIGN KEY (tenant_id) REFERENCES pa_tenant(id)
) ENGINE=InnoDB;
CREATE TABLE pa_dept (
 id INT UNSIGNED NOT NULL AUTO_INCREMENT, tenant_id BIGINT UNSIGNED NOT NULL, pid INT UNSIGNED NOT NULL DEFAULT 0,
 name VARCHAR(50) NOT NULL DEFAULT '', leader VARCHAR(50) NOT NULL DEFAULT '', mobile VARCHAR(20) NOT NULL DEFAULT '',
 active_name VARCHAR(50) GENERATED ALWAYS AS (IF(delete_time IS NULL, name, NULL)) STORED,
 sort SMALLINT NOT NULL DEFAULT 0, is_disable TINYINT NOT NULL DEFAULT 0, status TINYINT NOT NULL DEFAULT 1,
 create_time INT UNSIGNED NOT NULL DEFAULT 0, update_time INT UNSIGNED NOT NULL DEFAULT 0, delete_time INT UNSIGNED NULL,
 PRIMARY KEY (id), UNIQUE KEY uk_dept_tenant_id (tenant_id,id),
 UNIQUE KEY uk_dept_tenant_active_name (tenant_id,active_name),
 CONSTRAINT fk_dept_tenant FOREIGN KEY (tenant_id) REFERENCES pa_tenant(id)
) ENGINE=InnoDB;
CREATE TABLE pa_jobs (
 id INT UNSIGNED NOT NULL AUTO_INCREMENT, tenant_id BIGINT UNSIGNED NOT NULL, name VARCHAR(50) NOT NULL DEFAULT '',
 code VARCHAR(64) NOT NULL DEFAULT '', sort SMALLINT NOT NULL DEFAULT 0, is_disable TINYINT NOT NULL DEFAULT 0,
 active_name VARCHAR(50) GENERATED ALWAYS AS (IF(delete_time IS NULL, name, NULL)) STORED,
 active_code VARCHAR(64) GENERATED ALWAYS AS (IF(delete_time IS NULL, code, NULL)) STORED,
 status TINYINT NOT NULL DEFAULT 1, remark VARCHAR(200) NOT NULL DEFAULT '',
 create_time INT UNSIGNED NOT NULL DEFAULT 0, update_time INT UNSIGNED NOT NULL DEFAULT 0, delete_time INT UNSIGNED NULL,
 PRIMARY KEY (id), UNIQUE KEY uk_jobs_tenant_id (tenant_id,id),
 UNIQUE KEY uk_jobs_tenant_active_name (tenant_id,active_name),
 UNIQUE KEY uk_jobs_tenant_active_code (tenant_id,active_code),
 CONSTRAINT fk_jobs_tenant FOREIGN KEY (tenant_id) REFERENCES pa_tenant(id)
) ENGINE=InnoDB;
CREATE TABLE pa_system_menu (
 id INT UNSIGNED NOT NULL, pid INT UNSIGNED NOT NULL DEFAULT 0, type CHAR(1) NOT NULL DEFAULT 'C',
 name VARCHAR(50) NOT NULL DEFAULT '', icon VARCHAR(100) NOT NULL DEFAULT '', sort SMALLINT NOT NULL DEFAULT 0,
 perms VARCHAR(100) NOT NULL DEFAULT '', paths VARCHAR(200) NOT NULL DEFAULT '', component VARCHAR(200) NOT NULL DEFAULT '',
 is_cache TINYINT NOT NULL DEFAULT 0, is_show TINYINT NOT NULL DEFAULT 1, is_disable TINYINT NOT NULL DEFAULT 0,
 PRIMARY KEY (id)
) ENGINE=InnoDB;
CREATE TABLE pa_admin_role (
 id INT UNSIGNED NOT NULL AUTO_INCREMENT, tenant_id BIGINT UNSIGNED NOT NULL, admin_id INT UNSIGNED NOT NULL, role_id INT UNSIGNED NOT NULL,
 PRIMARY KEY (id), UNIQUE KEY uk_admin_role_tenant (tenant_id,admin_id,role_id),
 CONSTRAINT fk_admin_role_tenant FOREIGN KEY (tenant_id) REFERENCES pa_tenant(id),
 CONSTRAINT fk_admin_role_admin_owner FOREIGN KEY (tenant_id,admin_id) REFERENCES pa_admin(tenant_id,id) ON DELETE RESTRICT,
 CONSTRAINT fk_admin_role_role_owner FOREIGN KEY (tenant_id,role_id) REFERENCES pa_system_role(tenant_id,id) ON DELETE RESTRICT
) ENGINE=InnoDB;
CREATE TABLE pa_admin_dept (
 tenant_id BIGINT UNSIGNED NOT NULL, admin_id INT UNSIGNED NOT NULL, dept_id INT UNSIGNED NOT NULL,
 PRIMARY KEY (admin_id,dept_id), UNIQUE KEY uk_admin_dept_tenant (tenant_id,admin_id,dept_id),
 CONSTRAINT fk_admin_dept_tenant FOREIGN KEY (tenant_id) REFERENCES pa_tenant(id),
 CONSTRAINT fk_admin_dept_admin_owner FOREIGN KEY (tenant_id,admin_id) REFERENCES pa_admin(tenant_id,id) ON DELETE RESTRICT,
 CONSTRAINT fk_admin_dept_dept_owner FOREIGN KEY (tenant_id,dept_id) REFERENCES pa_dept(tenant_id,id) ON DELETE RESTRICT
) ENGINE=InnoDB;
CREATE TABLE pa_admin_jobs (
 tenant_id BIGINT UNSIGNED NOT NULL, admin_id INT UNSIGNED NOT NULL, jobs_id INT UNSIGNED NOT NULL,
 PRIMARY KEY (admin_id,jobs_id), UNIQUE KEY uk_admin_jobs_tenant (tenant_id,admin_id,jobs_id),
 CONSTRAINT fk_admin_jobs_tenant FOREIGN KEY (tenant_id) REFERENCES pa_tenant(id),
 CONSTRAINT fk_admin_jobs_admin_owner FOREIGN KEY (tenant_id,admin_id) REFERENCES pa_admin(tenant_id,id) ON DELETE RESTRICT,
 CONSTRAINT fk_admin_jobs_jobs_owner FOREIGN KEY (tenant_id,jobs_id) REFERENCES pa_jobs(tenant_id,id) ON DELETE RESTRICT
) ENGINE=InnoDB;
CREATE TABLE pa_system_role_menu (
 id INT UNSIGNED NOT NULL AUTO_INCREMENT, tenant_id BIGINT UNSIGNED NOT NULL, role_id INT UNSIGNED NOT NULL, menu_id INT UNSIGNED NOT NULL,
 PRIMARY KEY (id), UNIQUE KEY uk_role_menu_tenant (tenant_id,role_id,menu_id),
 CONSTRAINT fk_role_menu_tenant FOREIGN KEY (tenant_id) REFERENCES pa_tenant(id),
 CONSTRAINT fk_role_menu_role_owner FOREIGN KEY (tenant_id,role_id) REFERENCES pa_system_role(tenant_id,id) ON DELETE RESTRICT,
 CONSTRAINT fk_role_menu_menu FOREIGN KEY (menu_id) REFERENCES pa_system_menu(id) ON DELETE RESTRICT
) ENGINE=InnoDB;
CREATE TABLE pa_admin_session (
 id INT UNSIGNED NOT NULL AUTO_INCREMENT, admin_id INT UNSIGNED NOT NULL, terminal TINYINT NOT NULL DEFAULT 1,
 token VARCHAR(64) NOT NULL DEFAULT '', login_ip VARCHAR(45) NOT NULL DEFAULT '', update_time INT UNSIGNED NOT NULL DEFAULT 0,
 expire_time INT UNSIGNED NOT NULL DEFAULT 0, PRIMARY KEY (id), UNIQUE KEY uk_token (token)
) ENGINE=InnoDB;
CREATE TABLE pa_config (
 id INT UNSIGNED NOT NULL AUTO_INCREMENT, type VARCHAR(30) NOT NULL DEFAULT '',
 name VARCHAR(60) NOT NULL DEFAULT '', value TEXT,
 create_time INT UNSIGNED NOT NULL DEFAULT 0, update_time INT UNSIGNED NOT NULL DEFAULT 0,
 PRIMARY KEY (id), UNIQUE KEY uk_type_name (type,name)
) ENGINE=InnoDB;
INSERT INTO pa_config (type,name,value) VALUES ('storage','default','local');
INSERT INTO pa_tenant (id,status) VALUES (101,'active'),(202,'active');
INSERT INTO pa_system_menu (id,name,type) VALUES (1,'Dashboard','C');
SQL);
}

$serverRoot = dirname(__DIR__, 2);
$host = getenv('DB_HOST') ?: '127.0.0.1';
$port = (int)(getenv('DB_PORT') ?: 3306);
$password = getenv('MYSQL_ROOT_PASSWORD') ?: 'peanut_admin_root_dev';
$runId = strtolower(bin2hex(random_bytes(6)));
$adminPdo = new PDO(
    "mysql:host={$host};port={$port};charset=utf8mb4",
    'root',
    $password,
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::MYSQL_ATTR_MULTI_STATEMENTS => true]
);
$database = 'peanut_admin_mt02_org_' . $runId;

try {
    $adminPdo->exec("CREATE DATABASE `{$database}` CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci");
    $pdo = new PDO("mysql:host={$host};port={$port};dbname={$database};charset=utf8mb4", 'root', $password, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::MYSQL_ATTR_MULTI_STATEMENTS => true]);
    createOrgTenantSchema($pdo);

    putenv('PHP_DB_HOST=' . $host);
    putenv('PHP_DB_PORT=' . $port);
    putenv('PHP_DB_NAME=' . $database);
    putenv('PHP_DB_USER=root');
    putenv('PHP_DB_PASS=' . $password);
    putenv('PHP_DB_PREFIX=pa_');
    $app = new think\App($serverRoot);
    $app->initialize();

    $alpha = orgTenantContext(101, 501, 'mt02-org-alpha-' . $runId);
    $beta = orgTenantContext(202, 502, 'mt02-org-beta-' . $runId);
    try {
        OrgTenantContext::member(new stdClass());
        throw new RuntimeException('missing TenantContext reached org Runtime');
    } catch (Throwable $exception) {
        expectOrgTenant($exception->getMessage() !== '', 'missing context denial lost shape');
    }

    foreach ([[$alpha, 202], [$beta, 101]] as [$context, $payloadTenantId]) {
        expectOrgTenant(RoleLogic::add($context, ['tenant_id' => $payloadTenantId, 'name' => 'Manager', 'menu_id' => [1]]), RoleLogic::getError());
        expectOrgTenant(DeptLogic::add($context, ['tenant_id' => $payloadTenantId, 'pid' => 0, 'name' => 'Operations', 'status' => 1]), DeptLogic::getError());
        expectOrgTenant(JobsLogic::add($context, ['tenant_id' => $payloadTenantId, 'name' => 'Operator', 'code' => 'OPS', 'status' => 1]), JobsLogic::getError());
    }

    $alphaRole = (int)SystemRole::where(['tenant_id' => 101, 'name' => 'Manager'])->value('id');
    $betaRole = (int)SystemRole::where(['tenant_id' => 202, 'name' => 'Manager'])->value('id');
    $alphaDept = (int)Dept::where(['tenant_id' => 101, 'name' => 'Operations'])->value('id');
    $betaDept = (int)Dept::where(['tenant_id' => 202, 'name' => 'Operations'])->value('id');
    $alphaJobs = (int)Jobs::where(['tenant_id' => 101, 'code' => 'OPS'])->value('id');
    $betaJobs = (int)Jobs::where(['tenant_id' => 202, 'code' => 'OPS'])->value('id');
    expectOrgTenant($alphaRole > 0 && $betaRole > 0 && $alphaRole !== $betaRole, 'same role name was not Tenant-local');
    expectOrgTenant($alphaDept > 0 && $betaDept > 0 && $alphaDept !== $betaDept, 'same department name was not Tenant-local');
    expectOrgTenant($alphaJobs > 0 && $betaJobs > 0 && $alphaJobs !== $betaJobs, 'same job code was not Tenant-local');
    expectOrgTenant(RoleLogic::detail($alpha, $betaRole) === [], 'cross-Tenant role detail leaked');
    expectOrgTenant(DeptLogic::detail($alpha, $betaDept) === [], 'cross-Tenant department detail leaked');
    expectOrgTenant(JobsLogic::detail($alpha, $betaJobs) === [], 'cross-Tenant job detail leaked');
    expectOrgTenant(RoleLogic::lists($alpha, [])['count'] === 1, 'role list crossed Tenant boundary');
    expectOrgTenant(count(DeptLogic::lists($alpha)) === 1, 'department list crossed Tenant boundary');
    expectOrgTenant(JobsLogic::lists($alpha, ['export' => 1])['count'] === 1, 'job export query crossed Tenant boundary');

    expectOrgTenant(!AdminLogic::add($alpha, [
        'tenant_id' => 202, 'account' => 'blocked', 'name' => 'Blocked', 'password' => 'password123',
        'disable' => 0, 'multipoint_login' => 1, 'role_id' => [$betaRole], 'dept_id' => [$alphaDept], 'jobs_id' => [$alphaJobs],
    ]), 'cross-Tenant role assignment succeeded');
    expectOrgTenant(AdminLogic::add($alpha, [
        'tenant_id' => 202, 'account' => 'shared-admin', 'name' => 'Shared Admin', 'password' => 'password123',
        'disable' => 0, 'multipoint_login' => 1, 'role_id' => [$alphaRole], 'dept_id' => [$alphaDept], 'jobs_id' => [$alphaJobs],
    ]), AdminLogic::getError());
    expectOrgTenant(AdminLogic::add($beta, [
        'tenant_id' => 101, 'account' => 'shared-admin', 'name' => 'Shared Admin', 'password' => 'password123',
        'disable' => 0, 'multipoint_login' => 1, 'role_id' => [$betaRole], 'dept_id' => [$betaDept], 'jobs_id' => [$betaJobs],
    ]), AdminLogic::getError());
    $alphaAdmin = (int)Admin::where(['tenant_id' => 101, 'username' => 'shared-admin'])->value('id');
    $betaAdmin = (int)Admin::where(['tenant_id' => 202, 'username' => 'shared-admin'])->value('id');
    expectOrgTenant(AdminLogic::detail($alpha, $betaAdmin) === [], 'cross-Tenant admin detail leaked');
    $alphaAdminList = AdminLogic::lists($alpha, []);
    expectOrgTenant($alphaAdminList !== false, 'admin list failed: ' . AdminLogic::getError());
    expectOrgTenant($alphaAdminList['count'] === 1, 'admin list crossed Tenant boundary');
    expectOrgTenant(!AdminLogic::updateStatus($alpha, $betaAdmin, 1), 'cross-Tenant admin status changed');
    expectOrgTenant(!AdminLogic::delete($alpha, $betaAdmin), 'cross-Tenant admin delete succeeded');
    expectOrgTenant((int)Admin::where('id', $betaAdmin)->value('disable') === 0, 'cross-Tenant status denial mutated target');

    foreach ([
        "INSERT INTO pa_admin_role (tenant_id,admin_id,role_id) VALUES (202,{$alphaAdmin},{$betaRole})",
        "INSERT INTO pa_admin_dept (tenant_id,admin_id,dept_id) VALUES (202,{$alphaAdmin},{$betaDept})",
        "INSERT INTO pa_admin_jobs (tenant_id,admin_id,jobs_id) VALUES (202,{$alphaAdmin},{$betaJobs})",
        "INSERT INTO pa_system_role_menu (tenant_id,role_id,menu_id) VALUES (202,{$alphaRole},1)",
    ] as $pollution) {
        try {
            $pdo->exec($pollution);
            throw new RuntimeException('database accepted cross-Tenant pivot pollution');
        } catch (PDOException $exception) {
            expectOrgTenant($exception->getCode() === '23000', 'pivot pollution failed with unexpected shape');
        }
    }

    expectOrgTenant(AdminRole::where(['tenant_id' => 101, 'admin_id' => $alphaAdmin, 'role_id' => $alphaRole])->count() === 1, 'owned admin role relation missing');
    expectOrgTenant(RoleLogic::edit($alpha, ['id' => $alphaRole, 'name' => 'Manager Alpha', 'menu_id' => [1]]), RoleLogic::getError());
    expectOrgTenant(DeptLogic::edit($alpha, ['id' => $alphaDept, 'pid' => 0, 'name' => 'Operations Alpha', 'status' => 1]), DeptLogic::getError());
    expectOrgTenant(JobsLogic::edit($alpha, ['id' => $alphaJobs, 'name' => 'Operator Alpha', 'code' => 'OPS-A', 'status' => 1]), JobsLogic::getError());
    expectOrgTenant(JobsLogic::updateStatus($alpha, $alphaJobs, 0), JobsLogic::getError());
} finally {
    $adminPdo->exec("DROP DATABASE IF EXISTS `{$database}`");
}

echo "MT02-ORG-TENANT-ISOLATION-001 passed\n";
