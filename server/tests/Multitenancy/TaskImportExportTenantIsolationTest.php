<?php
declare(strict_types=1);

use app\common\service\authorization\AdminAuthorizationService;
use app\Modules\Official\ImportExport\Application\TaskImportExportRuntime;
use app\Modules\Official\ImportExport\Infrastructure\Authorization\AdminAsyncAuthorization;
use app\Modules\Official\Task\ModuleProvider as TaskModuleProvider;
use app\Modules\Official\ImportExport\Contracts\Dto\CsvExportOperation;
use PeanutAdmin\ImportExport\Application\ImportExportService;
use PeanutAdmin\Kernel\Async\VerifiedJobEnvelope;
use PeanutAdmin\Kernel\Context\RequestedTargetSet;
use PeanutAdmin\Kernel\Context\AuthorizedOperationContext;
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
        new DateTimeImmutable('2031-01-01T00:00:00Z'),
        1,
    ), $requestId);
}

function submitOperationLogExport(TaskImportExportRuntime $runtime, AuthorizedOperationContext $context, string $idempotencyKey): object
{
    return $runtime->commands()->submitCsvExport(
        $context,
        CsvExportOperation::operationLog($idempotencyKey),
    );
}

function asyncTenantSchema(PDO $pdo, string $serverRoot): void
{
    foreach (KernelSchema::tableNames() as $table) {
        $pdo->exec(KernelSchema::createSql($table));
    }
    $pdo->exec(KernelSchema::addTenantMemberDepartmentForeignKeySql());
    $pdo->exec(<<<'SQL'
INSERT INTO pa_tenant
  (id, code, name, display_name, status, activated_at, created_at, updated_at)
VALUES
  (101, 'default', 'Alpha', 'Alpha', 'active', UTC_TIMESTAMP(3), UTC_TIMESTAMP(3), UTC_TIMESTAMP(3));
SQL);
    $schema = (string)file_get_contents($serverRoot . '/database/init.sql');
    expectAsyncTenant($schema !== '', 'canonical application schema is missing');
    $pdo->exec($schema);
}

$serverRoot = dirname(__DIR__, 2);
$manifestLoader = new PeanutAdmin\Kernel\Module\ManifestLoader();
$taskManifest = $manifestLoader->load($serverRoot . '/app/Modules/Official/Task');
$importExportManifest = $manifestLoader->load($serverRoot . '/app/Modules/Official/ImportExport');
$taskVersion = (string)($taskManifest->data['version'] ?? '');
$importExportVersion = (string)($importExportManifest->data['version'] ?? '');
$taskManifestDigest = $taskManifest->digest;
$importExportManifestDigest = $importExportManifest->digest;
expectAsyncTenant($taskVersion !== '' && $taskManifestDigest !== '', 'official.task Module manifest is unavailable');
expectAsyncTenant($importExportVersion !== '' && $importExportManifestDigest !== '', 'official.import-export Module manifest is unavailable');
$host = (string)getenv('DB_HOST');
$port = (int)getenv('DB_PORT');
$database = (string)getenv('DB_NAME');
$user = (string)getenv('DB_USER');
$password = (string)getenv('DB_PASS');
$runId = strtolower(bin2hex(random_bytes(6)));
$privateRoot = (string)getenv('ASYNC_PRIVATE_ROOT');
$signingKey = hash('sha256', 'fresh-async-' . $runId) . hash('sha256', 'second-' . $runId);

expectAsyncTenant($host !== '' && $port > 0 && $user !== '' && $password !== '', 'registered P0-E database credentials are required');
expectAsyncTenant(
    preg_match('/^peanut_admin_development_p0e_[a-z0-9]{1,11}_plugin_lifecycle$/D', $database) === 1,
    'Task async Gate requires its exact registered P0-E plugin_lifecycle database'
);
expectAsyncTenant($privateRoot !== '' && !file_exists($privateRoot), 'lease-owned async private root must be absent');

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
    asyncTenantSchema($pdo, $serverRoot);
    $now = '2030-01-01 00:00:00.000';
    $pdo->exec(<<<SQL
INSERT INTO pa_tenant
  (id, code, name, display_name, status, activated_at, created_at, updated_at)
VALUES
  (202, 'beta', 'Beta', 'Beta', 'active', '{$now}', '{$now}', '{$now}');
INSERT INTO pa_account (id, display_name, status, created_at, updated_at) VALUES
  (1001, 'Alpha', 'active', '{$now}', '{$now}'),
  (1002, 'Beta', 'active', '{$now}', '{$now}'),
  (1003, 'No export', 'active', '{$now}', '{$now}');
INSERT INTO pa_credential
  (account_id, kind, identifier_type, identifier_normalized, secret_hash, status, verified_at, secret_changed_at, created_at, updated_at)
VALUES
  (1001, 'email_password', 'email', 'alpha@example.test', REPEAT('a', 64), 'active', '{$now}', '{$now}', '{$now}', '{$now}'),
  (1002, 'email_password', 'email', 'beta@example.test', REPEAT('b', 64), 'active', '{$now}', '{$now}', '{$now}', '{$now}'),
  (1003, 'email_password', 'email', 'no-export@example.test', REPEAT('c', 64), 'active', '{$now}', '{$now}', '{$now}', '{$now}');
INSERT INTO pa_tenant_member
  (id, tenant_id, account_id, display_name, status, joined_at, created_at, updated_at)
VALUES
  (501, 101, 1001, 'Alpha', 'active', '{$now}', '{$now}', '{$now}'),
  (502, 202, 1002, 'Beta', 'active', '{$now}', '{$now}', '{$now}'),
  (503, 101, 1003, 'No export', 'active', '{$now}', '{$now}', '{$now}');
INSERT INTO pa_module_installation
  (module_key, installed_version, manifest_schema_version, manifest_digest, status, installed_at, activated_at, created_at, updated_at)
VALUES
  ('official.task', '{$taskVersion}', 1, '{$taskManifestDigest}', 'active', '{$now}', '{$now}', '{$now}', '{$now}'),
  ('official.import-export', '{$importExportVersion}', 1, '{$importExportManifestDigest}', 'active', '{$now}', '{$now}', '{$now}', '{$now}');
INSERT INTO pa_tenant_module
  (tenant_id, module_key, status, source, enabled_at, created_at, updated_at)
VALUES
  (101, 'official.task', 'enabled', 'manual', '{$now}', '{$now}', '{$now}'),
  (202, 'official.task', 'enabled', 'manual', '{$now}', '{$now}', '{$now}'),
  (101, 'official.import-export', 'enabled', 'manual', '{$now}', '{$now}', '{$now}'),
  (202, 'official.import-export', 'enabled', 'manual', '{$now}', '{$now}', '{$now}');
INSERT INTO pa_permission
  (`key`, module_key, type, name, description, risk_level, status, manifest_version, created_at, updated_at)
VALUES
  ('official.import-export.operation-log.export', 'official.import-export', 'api', 'Export operation logs', NULL, 'normal', 'active', 'fresh-schema-v1', '{$now}', '{$now}')
ON DUPLICATE KEY UPDATE status = 'active', updated_at = VALUES(updated_at), retired_at = NULL;
INSERT INTO pa_role
  (id, tenant_id, `key`, name, is_builtin, status, authorization_revision, created_at, updated_at)
VALUES
  (11, 101, 'alpha.export', 'Alpha export', 0, 'active', 1, '{$now}', '{$now}'),
  (22, 202, 'beta.export', 'Beta export', 0, 'active', 1, '{$now}', '{$now}'),
  (33, 101, 'alpha.no-export', 'Alpha no export', 0, 'active', 1, '{$now}', '{$now}');
INSERT INTO pa_member_role (tenant_id, tenant_member_id, role_id, assigned_at) VALUES
  (101, 501, 11, '{$now}'),
  (202, 502, 22, '{$now}'),
  (101, 503, 33, '{$now}');
INSERT INTO pa_system_menu (type, name, perms, paths, is_disable)
VALUES ('A', 'Export operation logs', 'official.import-export.operation-log.export', '', 0);
SQL);
    $exportPermission = (int)$pdo->query("SELECT id FROM pa_permission WHERE `key` = 'official.import-export.operation-log.export'")->fetchColumn();
    expectAsyncTenant($exportPermission > 0, 'fresh canonical async export Permission is missing');
    $insertPermission = $pdo->prepare(
        'INSERT INTO pa_role_permission (tenant_id, role_id, permission_id, granted_at) VALUES (?, ?, ?, ?)'
    );
    $insertPermission->execute([101, 11, $exportPermission, $now]);

    $insertPermission->execute([202, 22, $exportPermission, $now]);
    $pdo->exec(<<<'SQL'
INSERT INTO pa_operation_log (tenant_id, admin_id, username, uri, method, params, create_time) VALUES
  (101, 501, 'alpha', 'same/write', 'POST', '{"marker":"alpha-only"}', UNIX_TIMESTAMP()),
  (202, 502, 'beta', 'same/write', 'POST', '{"marker":"beta-only"}', UNIX_TIMESTAMP());
SQL);

    putenv('PHP_DB_HOST=' . $host);
    putenv('PHP_DB_PORT=' . $port);
    putenv('PHP_DB_NAME=' . $database);
    putenv('PHP_DB_USER=root');
    putenv('PHP_DB_PASS=' . $password);
    putenv('PHP_DB_PREFIX=pa_');
    $app = new think\App($serverRoot);
    $app->initialize();

    $alphaTenant = asyncTenantContext(101, 1001, 501, 'fresh-async-alpha-' . $runId);
    $betaTenant = asyncTenantContext(202, 1002, 502, 'fresh-async-beta-' . $runId);
    $noExportTenant = asyncTenantContext(101, 1003, 503, 'fresh-async-no-export-' . $runId);
    $authorization = new AdminAuthorizationService($pdo);
    $alpha = $authorization->authorizedAsyncExport($alphaTenant, $authorization->principal($alphaTenant));
    $beta = $authorization->authorizedAsyncExport($betaTenant, $authorization->principal($betaTenant));
    try {
        $authorization->authorizedAsyncExport($noExportTenant, $authorization->principal($noExportTenant));
        throw new RuntimeException('native permission-less async submission succeeded');
    } catch (DomainException $exception) {
        expectAsyncTenant($exception->getMessage() === 'ASYNC_EXPORT_PERMISSION_DENIED', 'native permission denial shape changed');
    }

    $revalidator = new AdminAsyncAuthorization($pdo);
    $validEnvelope = new VerifiedJobEnvelope(
        101,
        1001,
        501,
        ImportExportService::RESOURCE_KEY,
        'create',
        [],
        'envelope-' . $runId,
        'trace-' . $runId,
    );
    $reauthorized = $revalidator->reauthorize($validEnvelope);
    expectAsyncTenant($reauthorized->resourceKey === ImportExportService::RESOURCE_KEY, 'valid async resource changed');
    expectAsyncTenant($reauthorized->operation === 'create' && $reauthorized->targets === [], 'valid async operation changed');
    foreach ([
        new VerifiedJobEnvelope(101, 1001, 501, 'wrong.resource', 'create', [], 'bad-resource', 'trace'),
        new VerifiedJobEnvelope(101, 1001, 501, ImportExportService::RESOURCE_KEY, 'delete', [], 'bad-operation', 'trace'),
        new VerifiedJobEnvelope(101, 1001, 501, ImportExportService::RESOURCE_KEY, 'create', [new RequestedTargetSet('file', ['1'])], 'bad-targets', 'trace'),
    ] as $invalidEnvelope) {
        try {
            $revalidator->reauthorize($invalidEnvelope);
            throw new RuntimeException('invalid async envelope was accepted');
        } catch (PeanutAdmin\Kernel\Auth\AuthException) {
            // Expected: the signed envelope fields are part of the authorization contract.
        }
    }

    $runtime = new TaskImportExportRuntime(
        $pdo,
        (new TaskModuleProvider())->jobs($pdo, $signingKey),
    );
    $taskDisabled = submitOperationLogExport($runtime, $alpha, 'task-disabled-' . $runId);
    $pdo->exec("UPDATE pa_tenant_module SET status = 'disabled', disabled_at = UTC_TIMESTAMP(3) WHERE tenant_id = 101 AND module_key = 'official.task'");
    expectAsyncTenant($runtime->runTenant(101, 'fresh-task-disabled-' . $runId) === 1, 'disabled Task Module job was not examined');
    expectAsyncTenant($pdo->query("SELECT status FROM pa_task_job WHERE job_key = " . $pdo->quote((string)$taskDisabled->taskJobKey))->fetchColumn() === 'dead', 'disabled Task Module executed');
    $pdo->exec("UPDATE pa_tenant_module SET status = 'enabled', disabled_at = NULL WHERE tenant_id = 101 AND module_key = 'official.task'");

    $importExportDisabled = submitOperationLogExport($runtime, $alpha, 'import-export-disabled-' . $runId);
    $pdo->exec("UPDATE pa_tenant_module SET status = 'disabled', disabled_at = UTC_TIMESTAMP(3) WHERE tenant_id = 101 AND module_key = 'official.import-export'");
    expectAsyncTenant($runtime->runTenant(101, 'fresh-import-export-disabled-' . $runId) === 1, 'disabled Import/Export Module job was not examined');
    expectAsyncTenant($pdo->query("SELECT status FROM pa_task_job WHERE job_key = " . $pdo->quote((string)$importExportDisabled->taskJobKey))->fetchColumn() === 'dead', 'disabled Import/Export Module executed');
    $pdo->exec("UPDATE pa_tenant_module SET status = 'enabled', disabled_at = NULL WHERE tenant_id = 101 AND module_key = 'official.import-export'");

    $operationCountBeforeIdempotency = (int)$pdo->query('SELECT COUNT(*) FROM pa_import_export_operation')->fetchColumn();
    $jobCountBeforeIdempotency = (int)$pdo->query('SELECT COUNT(*) FROM pa_task_job')->fetchColumn();
    $operation = submitOperationLogExport($runtime, $alpha, 'alpha-idempotency-' . $runId);
    $duplicate = submitOperationLogExport($runtime, $alpha, 'alpha-idempotency-' . $runId);
    expectAsyncTenant($duplicate->operationKey === $operation->operationKey, 'idempotent submission created a second operation');
    expectAsyncTenant((int)$pdo->query('SELECT COUNT(*) FROM pa_import_export_operation')->fetchColumn() === $operationCountBeforeIdempotency + 1, 'idempotent operation count changed');
    expectAsyncTenant((int)$pdo->query('SELECT COUNT(*) FROM pa_task_job')->fetchColumn() === $jobCountBeforeIdempotency + 1, 'idempotent job count changed');

    $operationCountAfterIdempotency = (int)$pdo->query('SELECT COUNT(*) FROM pa_import_export_operation')->fetchColumn();
    $jobCountAfterIdempotency = (int)$pdo->query('SELECT COUNT(*) FROM pa_task_job')->fetchColumn();
    $atomicInjectionSkipped = false;
    try {
        $pdo->exec(<<<'SQL'
CREATE TRIGGER reject_async_submit_audit BEFORE INSERT ON pa_tenant_audit_event
FOR EACH ROW BEGIN
  IF NEW.event_type = 'tenant.import_export.submitted' THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'injected audit failure';
  END IF;
END
SQL);
    } catch (PDOException $exception) {
        if (!str_contains($exception->getMessage(), 'SUPER privilege') || !str_contains($exception->getMessage(), 'binary logging')) {
            throw $exception;
        }
        $atomicInjectionSkipped = true;
        echo "atomic-injection=skipped(resource privilege)\n";
    }
    if (!$atomicInjectionSkipped) {
        try {
        submitOperationLogExport($runtime, $alpha, 'atomic-failure-' . $runId);
            throw new RuntimeException('atomic failure injection unexpectedly committed');
        } catch (Throwable $exception) {
            expectAsyncTenant(str_contains($exception->getMessage(), 'injected audit failure'), 'atomic failure injection was not reached');
        }
        $pdo->exec('DROP TRIGGER reject_async_submit_audit');
        expectAsyncTenant((int)$pdo->query('SELECT COUNT(*) FROM pa_import_export_operation')->fetchColumn() === $operationCountAfterIdempotency, 'failed submission left an operation');
        expectAsyncTenant((int)$pdo->query('SELECT COUNT(*) FROM pa_task_job')->fetchColumn() === $jobCountAfterIdempotency, 'failed submission left a job');
    }

    $jobKey = (string)$operation->taskJobKey;
    $envelope = (string)$pdo->query("SELECT trusted_envelope FROM pa_task_job WHERE job_key = " . $pdo->quote($jobKey))->fetchColumn();
    $forged = str_replace('"tenant_id":101', '"tenant_id":202', $envelope);
    $pdo->prepare('UPDATE pa_task_job SET trusted_envelope = ? WHERE job_key = ?')->execute([$forged, $jobKey]);
    expectAsyncTenant($runtime->runTenant(101, 'fresh-forged-' . $runId) === 1, 'forged job was not claimed');
    expectAsyncTenant($pdo->query("SELECT status FROM pa_task_job WHERE job_key = " . $pdo->quote($jobKey))->fetchColumn() === 'dead', 'forged envelope did not fail closed');
    expectAsyncTenant((int)$pdo->query('SELECT COUNT(*) FROM pa_file_object')->fetchColumn() === 0, 'forged envelope produced an artifact');

    $suspended = submitOperationLogExport($runtime, $alpha, 'suspended-' . $runId);
    $pdo->exec("UPDATE pa_tenant SET status = 'suspended', suspended_at = UTC_TIMESTAMP(3) WHERE id = 101");
    expectAsyncTenant($runtime->runTenant(101, 'fresh-suspended-' . $runId) === 1, 'suspended Tenant job was not examined');
    expectAsyncTenant($pdo->query("SELECT status FROM pa_task_job WHERE job_key = " . $pdo->quote((string)$suspended->taskJobKey))->fetchColumn() === 'dead', 'suspended Tenant job executed');
    $pdo->exec("UPDATE pa_tenant SET status = 'active', suspended_at = NULL WHERE id = 101");

    $revoked = submitOperationLogExport($runtime, $alpha, 'revoked-' . $runId);
    $pdo->exec("DELETE FROM pa_role_permission WHERE tenant_id = 101 AND role_id = 11 AND permission_id = {$exportPermission}");
    expectAsyncTenant($runtime->runTenant(101, 'fresh-revoked-' . $runId) === 1, 'revoked job was not examined');
    expectAsyncTenant($pdo->query("SELECT status FROM pa_task_job WHERE job_key = " . $pdo->quote((string)$revoked->taskJobKey))->fetchColumn() === 'dead', 'revoked native Permission still executed');
    $insertPermission->execute([101, 11, $exportPermission, $now]);

    $success = submitOperationLogExport($runtime, $alpha, 'success-' . $runId);
    expectAsyncTenant($runtime->runTenant(101, 'fresh-success-' . $runId) === 1, 'successful job was not processed');
    $completed = $runtime->operation($alpha, $success->operationKey);
    expectAsyncTenant($completed->status === 'succeeded' && $completed->resultFileKey !== null, 'successful export did not publish a result');
    $download = $runtime->download($alpha, $completed->resultFileKey);
    expectAsyncTenant(isset($download['url']) && is_string($download['url']) && str_contains($download['url'], '/api/storage/private?'), 'private CSV URL is missing');
    expectAsyncTenant(!str_contains((string)$download['url'], '/public/'), 'CSV was exposed below public/');
    try {
        $runtime->download($beta, $completed->resultFileKey);
        throw new RuntimeException('cross-Tenant result download succeeded');
    } catch (Throwable $exception) {
        expectAsyncTenant($exception->getMessage() === 'IMPORT_EXPORT_FILE_UNAVAILABLE', 'cross-Tenant download enumerated ownership');
    }
} finally {
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
