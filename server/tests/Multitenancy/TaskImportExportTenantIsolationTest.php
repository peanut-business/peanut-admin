<?php
declare(strict_types=1);

use app\adminapi\service\AdminPermissionService;
use app\common\service\async\TaskImportExportRuntime;
use PeanutAdmin\Kernel\Auth\TenantContext;
use PeanutAdmin\Kernel\Auth\ValidatedTenantSession;
use PeanutAdmin\Kernel\Persistence\Schema\KernelSchema;

require dirname(__DIR__, 2) . '/vendor/autoload.php';

function expectAsyncTenant(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function asyncTenantContext(int $tenantId, int $accountId, int $memberId, string $requestId): TenantContext
{
    return TenantContext::fromValidatedSession(new ValidatedTenantSession(
        $memberId,
        'async-session-' . $tenantId . '-' . $memberId,
        $tenantId,
        $accountId,
        $memberId,
        'admin-web',
        new \DateTimeImmutable('2031-01-01T00:00:00Z'),
        1,
    ), $requestId);
}

function asyncTenantSchema(PDO $pdo): void
{
    foreach (['pa_account', 'pa_tenant', 'pa_tenant_member', 'pa_tenant_audit_event'] as $table) {
        $pdo->exec(KernelSchema::createSql($table));
    }
    $pdo->exec(<<<'SQL'
CREATE TABLE pa_admin (
  id INT UNSIGNED NOT NULL,
  tenant_id BIGINT UNSIGNED NOT NULL,
  username VARCHAR(64) NOT NULL,
  root TINYINT UNSIGNED NOT NULL DEFAULT 0,
  disable TINYINT UNSIGNED NOT NULL DEFAULT 0,
  delete_time INT UNSIGNED NULL,
  PRIMARY KEY (id), UNIQUE KEY uk_admin_tenant_id (tenant_id, id)
) ENGINE=InnoDB;
CREATE TABLE pa_system_role (
  id INT UNSIGNED NOT NULL,
  tenant_id BIGINT UNSIGNED NOT NULL,
  name VARCHAR(64) NOT NULL,
  is_disable TINYINT UNSIGNED NOT NULL DEFAULT 0,
  delete_time INT UNSIGNED NULL,
  PRIMARY KEY (id), UNIQUE KEY uk_system_role_tenant_id (tenant_id, id)
) ENGINE=InnoDB;
CREATE TABLE pa_system_menu (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  pid INT UNSIGNED NOT NULL DEFAULT 0,
  type CHAR(1) NOT NULL,
  name VARCHAR(100) NOT NULL,
  icon VARCHAR(100) NOT NULL DEFAULT '',
  sort INT NOT NULL DEFAULT 0,
  perms VARCHAR(200) NOT NULL DEFAULT '',
  paths VARCHAR(200) NOT NULL DEFAULT '',
  component VARCHAR(200) NOT NULL DEFAULT '',
  is_cache TINYINT NOT NULL DEFAULT 0,
  is_show TINYINT NOT NULL DEFAULT 1,
  is_disable TINYINT NOT NULL DEFAULT 0,
  PRIMARY KEY (id)
) ENGINE=InnoDB;
CREATE TABLE pa_system_role_menu (
  tenant_id BIGINT UNSIGNED NOT NULL,
  role_id INT UNSIGNED NOT NULL,
  menu_id INT UNSIGNED NOT NULL,
  UNIQUE KEY uk_role_menu_tenant (tenant_id, role_id, menu_id)
) ENGINE=InnoDB;
CREATE TABLE pa_admin_role (
  tenant_id BIGINT UNSIGNED NOT NULL,
  admin_id INT UNSIGNED NOT NULL,
  role_id INT UNSIGNED NOT NULL,
  UNIQUE KEY uk_admin_role_tenant (tenant_id, admin_id, role_id)
) ENGINE=InnoDB;
CREATE TABLE pa_legacy_admin_tenant_map (
  tenant_id BIGINT UNSIGNED NOT NULL,
  legacy_admin_id INT UNSIGNED NOT NULL,
  account_id BIGINT UNSIGNED NOT NULL,
  tenant_member_id BIGINT UNSIGNED NOT NULL,
  PRIMARY KEY (tenant_id, legacy_admin_id),
  UNIQUE KEY uk_legacy_admin_member (tenant_id, tenant_member_id)
) ENGINE=InnoDB;
CREATE TABLE pa_operation_log (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  tenant_id BIGINT UNSIGNED NOT NULL,
  admin_id INT UNSIGNED NOT NULL DEFAULT 0,
  username VARCHAR(50) NOT NULL DEFAULT '',
  ip VARCHAR(50) NOT NULL DEFAULT '',
  uri VARCHAR(200) NOT NULL DEFAULT '',
  method VARCHAR(10) NOT NULL DEFAULT '',
  params TEXT,
  create_time INT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (id), UNIQUE KEY uk_operation_log_tenant_id (tenant_id, id)
) ENGINE=InnoDB;
SQL);
}

$serverRoot = dirname(__DIR__, 2);
$host = getenv('DB_HOST') ?: '127.0.0.1';
$port = (int)(getenv('DB_PORT') ?: 3306);
$password = getenv('MYSQL_ROOT_PASSWORD') ?: 'peanut_admin_root_dev';
$runId = strtolower(bin2hex(random_bytes(6)));
$database = 'peanut_admin_mt03_async_' . $runId;
$privateRoot = sys_get_temp_dir() . '/peanut-admin-mt03-async-' . $runId;
$signingKey = hash('sha256', 'mt03-async-' . $runId) . hash('sha256', 'second-' . $runId);

$admin = new PDO(
    "mysql:host={$host};port={$port};charset=utf8mb4",
    'root',
    $password,
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
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
    asyncTenantSchema($pdo);
    $now = '2030-01-01 00:00:00.000';
    $pdo->exec("INSERT INTO pa_account (id,display_name,status,created_at,updated_at) VALUES (1001,'Alpha','active','{$now}','{$now}'),(1002,'Beta','active','{$now}','{$now}')");
    $pdo->exec("INSERT INTO pa_tenant (id,code,name,display_name,status,activated_at,created_at,updated_at) VALUES (101,'alpha','Alpha','Alpha','active','{$now}','{$now}','{$now}'),(202,'beta','Beta','Beta','active','{$now}','{$now}','{$now}')");
    $pdo->exec("INSERT INTO pa_tenant_member (id,tenant_id,account_id,status,joined_at,created_at,updated_at) VALUES (501,101,1001,'active','{$now}','{$now}','{$now}'),(502,202,1002,'active','{$now}','{$now}','{$now}')");
    $pdo->exec("INSERT INTO pa_admin (id,tenant_id,username,root) VALUES (1,101,'alpha',0),(2,202,'beta',0)");
    $pdo->exec("INSERT INTO pa_system_role (id,tenant_id,name) VALUES (11,101,'Alpha export'),(22,202,'Beta export')");
    $pdo->exec("INSERT INTO pa_admin_role (tenant_id,admin_id,role_id) VALUES (101,1,11),(202,2,22)");
    $pdo->exec("INSERT INTO pa_legacy_admin_tenant_map (tenant_id,legacy_admin_id,account_id,tenant_member_id) VALUES (101,1,1001,501),(202,2,1002,502)");
    $pdo->exec("INSERT INTO pa_system_menu (type,name,paths) VALUES ('C','Operation logs','/system/log')");

    $migration = (string)file_get_contents($serverRoot . '/database/migrations/20260813_task_import_export.sql');
    expectAsyncTenant($migration !== '', 'async migration is missing');
    $pdo->exec($migration);
    $exportMenu = (int)$pdo->query("SELECT id FROM pa_system_menu WHERE perms='log/export'")->fetchColumn();
    expectAsyncTenant($exportMenu > 0, 'async export Permission was not registered');
    $insertPermission = $pdo->prepare('INSERT INTO pa_system_role_menu (tenant_id,role_id,menu_id) VALUES (?,?,?)');
    $insertPermission->execute([101, 11, $exportMenu]);
    $insertPermission->execute([202, 22, $exportMenu]);

    $pdo->exec("INSERT INTO pa_operation_log (tenant_id,admin_id,username,uri,method,params,create_time) VALUES (101,1,'alpha','same/write','POST','{\"marker\":\"alpha-only\"}',UNIX_TIMESTAMP()),(202,2,'beta','same/write','POST','{\"marker\":\"beta-only\"}',UNIX_TIMESTAMP())");

    putenv('PHP_DB_HOST=' . $host);
    putenv('PHP_DB_PORT=' . $port);
    putenv('PHP_DB_NAME=' . $database);
    putenv('PHP_DB_USER=root');
    putenv('PHP_DB_PASS=' . $password);
    putenv('PHP_DB_PREFIX=pa_');
    // The dependency directory can be shared between worktrees; bind config to this fixture root.
    $app = new think\App($serverRoot);
    $app->initialize();

    $alphaTenant = asyncTenantContext(101, 1001, 501, 'mt03-alpha-' . $runId);
    $betaTenant = asyncTenantContext(202, 1002, 502, 'mt03-beta-' . $runId);
    $alpha = AdminPermissionService::authorizedAsyncExport($alphaTenant, [
        'id' => 1, 'tenant_id' => 101, 'root' => 0, 'roles' => [['id' => 11]],
    ]);
    $beta = AdminPermissionService::authorizedAsyncExport($betaTenant, [
        'id' => 2, 'tenant_id' => 202, 'root' => 0, 'roles' => [['id' => 22]],
    ]);
    try {
        AdminPermissionService::authorizedAsyncExport($alphaTenant, [
            'id' => 1, 'tenant_id' => 101, 'root' => 0, 'roles' => [],
        ]);
        throw new RuntimeException('permission-less async submission succeeded');
    } catch (DomainException $exception) {
        expectAsyncTenant($exception->getMessage() === 'ASYNC_EXPORT_PERMISSION_DENIED', 'permission denial shape changed');
    }

    $runtime = new TaskImportExportRuntime($pdo, $signingKey, $privateRoot);
    $operation = $runtime->submitOperationLogExport($alpha, 'alpha-idempotency-' . $runId);
    $duplicate = $runtime->submitOperationLogExport($alpha, 'alpha-idempotency-' . $runId);
    expectAsyncTenant($duplicate->operationKey === $operation->operationKey, 'idempotent submission created a second operation');
    expectAsyncTenant((int)$pdo->query('SELECT COUNT(*) FROM pa_import_export_operation')->fetchColumn() === 1, 'idempotent operation count changed');
    expectAsyncTenant((int)$pdo->query('SELECT COUNT(*) FROM pa_task_job')->fetchColumn() === 1, 'idempotent job count changed');

    $pdo->exec(<<<'SQL'
CREATE TRIGGER reject_async_submit_audit BEFORE INSERT ON pa_tenant_audit_event
FOR EACH ROW BEGIN
  IF NEW.event_type = 'tenant.import_export.submitted' THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'injected audit failure';
  END IF;
END
SQL);
    try {
        $runtime->submitOperationLogExport($alpha, 'atomic-failure-' . $runId);
        throw new RuntimeException('atomic failure injection unexpectedly committed');
    } catch (Throwable $exception) {
        expectAsyncTenant(str_contains($exception->getMessage(), 'injected audit failure'), 'atomic failure injection was not reached');
    }
    $pdo->exec('DROP TRIGGER reject_async_submit_audit');
    expectAsyncTenant((int)$pdo->query('SELECT COUNT(*) FROM pa_import_export_operation')->fetchColumn() === 1, 'failed submission left an operation');
    expectAsyncTenant((int)$pdo->query('SELECT COUNT(*) FROM pa_task_job')->fetchColumn() === 1, 'failed submission left a job');

    $jobKey = (string)$operation->taskJobKey;
    $envelope = (string)$pdo->query("SELECT trusted_envelope FROM pa_task_job WHERE job_key=" . $pdo->quote($jobKey))->fetchColumn();
    $forged = str_replace('"tenant_id":101', '"tenant_id":202', $envelope);
    $pdo->prepare('UPDATE pa_task_job SET trusted_envelope=? WHERE job_key=?')->execute([$forged, $jobKey]);
    expectAsyncTenant($runtime->runTenant(101, 'mt03-forged-' . $runId) === 1, 'forged job was not claimed');
    expectAsyncTenant($pdo->query("SELECT status FROM pa_task_job WHERE job_key=" . $pdo->quote($jobKey))->fetchColumn() === 'dead', 'forged envelope did not fail closed');
    expectAsyncTenant((int)$pdo->query('SELECT COUNT(*) FROM pa_file_object')->fetchColumn() === 0, 'forged envelope produced an artifact');

    $suspended = $runtime->submitOperationLogExport($alpha, 'suspended-' . $runId);
    $pdo->exec("UPDATE pa_tenant SET status='suspended', suspended_at=UTC_TIMESTAMP(3) WHERE id=101");
    expectAsyncTenant($runtime->runTenant(101, 'mt03-suspended-' . $runId) === 1, 'suspended Tenant job was not examined');
    expectAsyncTenant($pdo->query("SELECT status FROM pa_task_job WHERE job_key=" . $pdo->quote((string)$suspended->taskJobKey))->fetchColumn() === 'dead', 'suspended Tenant job executed');
    $pdo->exec("UPDATE pa_tenant SET status='active', suspended_at=NULL WHERE id=101");

    $revoked = $runtime->submitOperationLogExport($alpha, 'revoked-' . $runId);
    $pdo->exec("DELETE FROM pa_system_role_menu WHERE tenant_id=101 AND role_id=11 AND menu_id={$exportMenu}");
    expectAsyncTenant($runtime->runTenant(101, 'mt03-revoked-' . $runId) === 1, 'revoked job was not examined');
    expectAsyncTenant($pdo->query("SELECT status FROM pa_task_job WHERE job_key=" . $pdo->quote((string)$revoked->taskJobKey))->fetchColumn() === 'dead', 'revoked Permission still executed');
    $insertPermission->execute([101, 11, $exportMenu]);

    $success = $runtime->submitOperationLogExport($alpha, 'success-' . $runId);
    expectAsyncTenant($runtime->runTenant(101, 'mt03-success-' . $runId) === 1, 'successful job was not processed');
    $completed = $runtime->operation($alpha, $success->operationKey);
    expectAsyncTenant($completed->status === 'succeeded' && $completed->resultFileKey !== null, 'successful export did not publish a result');
    $download = $runtime->download($alpha, $completed->resultFileKey);
    expectAsyncTenant(is_file($download['path']), 'private CSV is missing');
    expectAsyncTenant(str_starts_with($download['path'], $privateRoot . '/tenants/v1/101/exports/'), 'CSV escaped Tenant private namespace');
    expectAsyncTenant(!str_starts_with($download['path'], $serverRoot . '/public/'), 'CSV was stored below public/');
    $csv = (string)file_get_contents($download['path']);
    expectAsyncTenant(str_contains($csv, 'alpha-only'), 'Alpha CSV lost its own row');
    expectAsyncTenant(!str_contains($csv, 'beta-only'), 'Alpha CSV leaked Beta data');
    try {
        $runtime->download($beta, $completed->resultFileKey);
        throw new RuntimeException('cross-Tenant result download succeeded');
    } catch (Throwable $exception) {
        expectAsyncTenant($exception->getMessage() === 'IMPORT_EXPORT_FILE_UNAVAILABLE', 'cross-Tenant download enumerated ownership');
    }
} finally {
    $admin->exec("DROP DATABASE IF EXISTS `{$database}`");
    if (is_dir($privateRoot)) {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($privateRoot, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST,
        );
        foreach ($iterator as $item) {
            $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        }
        rmdir($privateRoot);
    }
}

echo "MT03-TASK-IMPORT-EXPORT-TENANT-ISOLATION-001 passed\n";
