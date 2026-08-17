<?php
declare(strict_types=1);

use app\adminapi\logic\auth\AdminLogic;
use app\adminapi\logic\auth\RoleLogic;
use app\adminapi\logic\dept\DeptLogic;
use app\adminapi\logic\dept\JobsLogic;
use app\common\service\org\OrgTenantContext;
use PeanutAdmin\Kernel\Migration\ModuleSchema;
use PeanutAdmin\Kernel\Persistence\Schema\KernelSchema;
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
    foreach (KernelSchema::tableNames() as $table) {
        $pdo->exec(KernelSchema::createSql($table));
    }
    $pdo->exec(KernelSchema::addTenantMemberDepartmentForeignKeySql());
    foreach (ModuleSchema::tableNames() as $table) {
        $pdo->exec(ModuleSchema::createSql($table));
    }
    $pdo->exec(<<<'SQL'
CREATE TABLE pa_jobs (
 id INT UNSIGNED NOT NULL AUTO_INCREMENT, tenant_id BIGINT UNSIGNED NOT NULL, name VARCHAR(50) NOT NULL DEFAULT '',
 code VARCHAR(64) NOT NULL DEFAULT '', sort SMALLINT NOT NULL DEFAULT 0, is_disable TINYINT NOT NULL DEFAULT 0,
 status TINYINT NOT NULL DEFAULT 1, remark VARCHAR(200) NOT NULL DEFAULT '',
 create_time INT UNSIGNED NOT NULL DEFAULT 0, update_time INT UNSIGNED NOT NULL DEFAULT 0, delete_time INT UNSIGNED NULL,
 PRIMARY KEY (id), KEY idx_jobs_tenant (tenant_id), UNIQUE KEY uk_jobs_tenant_code (tenant_id, code)
) ENGINE=InnoDB;
CREATE TABLE pa_system_menu (
 id INT UNSIGNED NOT NULL, pid INT UNSIGNED NOT NULL DEFAULT 0, type CHAR(1) NOT NULL DEFAULT 'C',
 name VARCHAR(50) NOT NULL DEFAULT '', icon VARCHAR(100) NOT NULL DEFAULT '', sort SMALLINT NOT NULL DEFAULT 0,
 perms VARCHAR(100) NOT NULL DEFAULT '', paths VARCHAR(200) NOT NULL DEFAULT '', component VARCHAR(200) NOT NULL DEFAULT '',
 is_cache TINYINT NOT NULL DEFAULT 0, is_show TINYINT NOT NULL DEFAULT 1, is_disable TINYINT NOT NULL DEFAULT 0,
 PRIMARY KEY (id)
) ENGINE=InnoDB;
INSERT INTO pa_tenant (id,code,name,display_name,status,activated_at,created_at,updated_at)
VALUES
 (101,'alpha','Alpha','Alpha','active',UTC_TIMESTAMP(3),UTC_TIMESTAMP(3),UTC_TIMESTAMP(3)),
 (202,'beta','Beta','Beta','active',UTC_TIMESTAMP(3),UTC_TIMESTAMP(3),UTC_TIMESTAMP(3));
INSERT INTO pa_account (id,display_name,created_at,updated_at) VALUES
 (1501,'Alpha Operator',UTC_TIMESTAMP(3),UTC_TIMESTAMP(3)),
 (1502,'Beta Operator',UTC_TIMESTAMP(3),UTC_TIMESTAMP(3));
INSERT INTO pa_credential (account_id,kind,identifier_type,identifier_normalized,secret_hash,verified_at,secret_changed_at,created_at,updated_at)
VALUES
 (1501,'email_password','email','alpha-operator@example.test','fixture',UTC_TIMESTAMP(3),UTC_TIMESTAMP(3),UTC_TIMESTAMP(3),UTC_TIMESTAMP(3)),
 (1502,'email_password','email','beta-operator@example.test','fixture',UTC_TIMESTAMP(3),UTC_TIMESTAMP(3),UTC_TIMESTAMP(3),UTC_TIMESTAMP(3));
INSERT INTO pa_tenant_member (id,tenant_id,account_id,member_no,display_name,status,joined_at,created_at,updated_at)
VALUES
 (501,101,1501,'alpha-operator','Alpha Operator','active',UTC_TIMESTAMP(3),UTC_TIMESTAMP(3),UTC_TIMESTAMP(3)),
 (502,202,1502,'beta-operator','Beta Operator','active',UTC_TIMESTAMP(3),UTC_TIMESTAMP(3),UTC_TIMESTAMP(3));
INSERT INTO pa_system_menu (id,type,name,is_disable) VALUES (1,'C','Dashboard',0);
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

    $alphaRole = (int)$pdo->query("SELECT id FROM pa_role WHERE tenant_id=101 AND name='Manager'")->fetchColumn();
    $betaRole = (int)$pdo->query("SELECT id FROM pa_role WHERE tenant_id=202 AND name='Manager'")->fetchColumn();
    $alphaDept = (int)$pdo->query("SELECT id FROM pa_department WHERE tenant_id=101 AND name='Operations'")->fetchColumn();
    $betaDept = (int)$pdo->query("SELECT id FROM pa_department WHERE tenant_id=202 AND name='Operations'")->fetchColumn();
    $alphaJobs = (int)$pdo->query("SELECT id FROM pa_jobs WHERE tenant_id=101 AND code='OPS'")->fetchColumn();
    $betaJobs = (int)$pdo->query("SELECT id FROM pa_jobs WHERE tenant_id=202 AND code='OPS'")->fetchColumn();
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
    $alphaAdmin = (int)$pdo->query("SELECT tm.id FROM pa_tenant_member tm JOIN pa_credential c ON c.account_id=tm.account_id WHERE tm.tenant_id=101 AND c.identifier_normalized='shared-admin'")->fetchColumn();
    $betaAdmin = (int)$pdo->query("SELECT tm.id FROM pa_tenant_member tm JOIN pa_credential c ON c.account_id=tm.account_id WHERE tm.tenant_id=202 AND c.identifier_normalized='shared-admin'")->fetchColumn();
    expectOrgTenant(AdminLogic::detail($alpha, $betaAdmin) === [], 'cross-Tenant admin detail leaked');
    $alphaAdminList = AdminLogic::lists($alpha, []);
    expectOrgTenant($alphaAdminList !== false, 'admin list failed: ' . AdminLogic::getError());
    expectOrgTenant($alphaAdminList['count'] === 1, 'admin list crossed Tenant boundary');
    expectOrgTenant(!AdminLogic::updateStatus($alpha, $betaAdmin, 1), 'cross-Tenant admin status changed');
    expectOrgTenant(!AdminLogic::delete($alpha, $betaAdmin), 'cross-Tenant admin delete succeeded');
    expectOrgTenant($pdo->query("SELECT status FROM pa_tenant_member WHERE tenant_id=202 AND id={$betaAdmin}")->fetchColumn() === 'active', 'cross-Tenant status denial mutated target');

    foreach ([
        "INSERT INTO pa_member_role (tenant_id,tenant_member_id,role_id,assigned_at) VALUES (202,{$alphaAdmin},{$betaRole},UTC_TIMESTAMP(3))",
        "UPDATE pa_tenant_member SET primary_department_id={$betaDept} WHERE tenant_id=101 AND id={$alphaAdmin}",
    ] as $pollution) {
        try {
            $pdo->exec($pollution);
            throw new RuntimeException('database accepted cross-Tenant pivot pollution');
        } catch (PDOException $exception) {
            expectOrgTenant($exception->getCode() === '23000', 'pivot pollution failed with unexpected shape');
        }
    }

    expectOrgTenant((int)$pdo->query("SELECT COUNT(*) FROM pa_member_role WHERE tenant_id=101 AND tenant_member_id={$alphaAdmin} AND role_id={$alphaRole}")->fetchColumn() === 1, 'owned admin role relation missing');
    expectOrgTenant(RoleLogic::edit($alpha, ['id' => $alphaRole, 'name' => 'Manager Alpha', 'menu_id' => [1]]), RoleLogic::getError());
    expectOrgTenant(DeptLogic::edit($alpha, ['id' => $alphaDept, 'pid' => 0, 'name' => 'Operations Alpha', 'status' => 1]), DeptLogic::getError());
    expectOrgTenant(JobsLogic::edit($alpha, ['id' => $alphaJobs, 'name' => 'Operator Alpha', 'code' => 'OPS-A', 'status' => 1]), JobsLogic::getError());
    expectOrgTenant(JobsLogic::updateStatus($alpha, $alphaJobs, 0), JobsLogic::getError());
} finally {
    $adminPdo->exec("DROP DATABASE IF EXISTS `{$database}`");
}

echo "MT02-ORG-TENANT-ISOLATION-001 passed\n";
