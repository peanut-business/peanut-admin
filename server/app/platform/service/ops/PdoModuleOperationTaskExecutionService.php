<?php
declare(strict_types=1);

namespace app\platform\service\ops;

use app\common\service\audit\AuditContractHost;
use DateTimeImmutable;
use DateTimeZone;
use PDO;
use PeanutAdmin\Kernel\Audit\AuditOutcome;
use PeanutAdmin\Kernel\Auth\ValidatedPlatformSession;
use PeanutAdmin\Kernel\Context\PlatformContext;
use PeanutAdmin\OpsConsole\Maintenance\MaintenanceWindow;
use PeanutAdmin\OpsConsole\Package;
use PeanutAdmin\OpsConsole\Task\OpsAuditEvent;
use PeanutAdmin\OpsConsole\Task\OpsTaskSubmission;
use PeanutAdmin\OpsConsole\Task\BackupRestoreProviderRegistry;
use Throwable;
use Closure;

/** Trusted state machine for one registry-bound Module delivery request. */
final readonly class PdoModuleOperationTaskExecutionService
{
    private const FAILURE_CODES = [
        'OPS_MODULE_PREFLIGHT_FAILED',
        'OPS_MODULE_BACKUP_FAILED',
        'OPS_MODULE_RESTORE_FAILED',
        'OPS_MODULE_MAINTENANCE_FAILED',
        'OPS_MODULE_EXECUTION_FAILED',
        'OPS_MODULE_SMOKE_FAILED',
        'OPS_MODULE_RECOVERY_POINTER_FAILED',
        'OPS_MODULE_WORKER_STALE',
        'OPS_MODULE_WORKER_FAILED',
    ];

    /** @param array<string,mixed> $moduleConfig @param array<string,string> $trustedKeys */
    public function __construct(
        private PDO $pdo,
        private string $projectRoot,
        private array $moduleConfig,
        private array $trustedKeys,
        private ?string $registryPath,
        private BackupRestoreProviderRegistry $backupProviders,
        private ApplicationRuntimeStatusProvider|Closure $runtimeStatus,
    ) {
    }

    /** @return array<string,mixed>|null */
    public function claim(): ?array
    {
        return $this->transaction(function (): ?array {
            $this->failStaleRunningTasks();
            $task = $this->one(<<<'SQL'
SELECT task.*, operator.account_id
FROM pa_ops_task task
JOIN pa_platform_operator operator ON operator.id=task.submitted_by_operator_id
WHERE task.task_type=:task_type AND task.handler_key=:handler_key
  AND task.status='queued' AND task.available_at<=UTC_TIMESTAMP(3)
ORDER BY task.id LIMIT 1 FOR UPDATE
SQL, [
                'task_type' => PlatformModuleOperationExecutionService::TASK_TYPE,
                'handler_key' => PlatformModuleOperationExecutionService::HANDLER_KEY,
            ]);
            if ($task === null) {
                return null;
            }
            $payload = $this->payload($task);
            $update = $this->pdo->prepare(<<<'SQL'
UPDATE pa_ops_task
SET status='running', attempt_count=attempt_count+1, revision=revision+1,
    updated_at=UTC_TIMESTAMP(3)
WHERE id=:id AND status='queued' AND revision=:revision
SQL);
            $update->execute(['id' => $task['id'], 'revision' => $task['revision']]);
            if ($update->rowCount() !== 1) {
                throw new \RuntimeException('OPS_MODULE_CLAIM_CONFLICT');
            }
            $revision = (int)$task['revision'] + 1;
            $insert = $this->pdo->prepare(<<<'SQL'
INSERT INTO pa_ops_module_execution (task_key,request_key,current_step)
VALUES (:task_key,:request_key,'preflight')
SQL);
            $insert->execute(['task_key' => $task['task_key'], 'request_key' => $payload['request_key']]);
            $this->audit($task, 'platform.ops.module.claimed', 'module.claim', [
                'task_key' => $task['task_key'],
                'request_key' => $payload['request_key'],
                'environment' => $payload['environment'],
                'target_resource_id' => $payload['target_resource_id'],
                'package_key' => $payload['package_key'],
                'operation' => $payload['operation'],
                'execution_revision' => $revision,
            ], AuditOutcome::Success, null);
            return [
                'task_key' => (string)$task['task_key'],
                'execution_revision' => $revision,
                'current_step' => 'preflight',
                'operation' => $payload['operation'],
                'package_key' => $payload['package_key'],
            ];
        });
    }

    /** @return array<string,mixed> */
    public function advance(string $taskKey, int $revision): array
    {
        $task = $this->runningTask($taskKey, $revision);
        return match ((string)$this->execution($taskKey)['current_step']) {
            'preflight' => $this->advancePreflight($task, $revision),
            'backup' => $this->advanceBackup($task, $revision),
            'restore_verification' => $this->advanceRestore($task, $revision),
            'maintenance' => $this->advanceMaintenance($task, $revision),
            'execution' => ['action' => 'execute'],
            'smoke' => ['action' => 'finalize'],
            default => throw new \RuntimeException('OPS_MODULE_STEP_INVALID'),
        };
    }

    /** @return array<string,mixed> */
    public function execute(string $taskKey, int $revision): array
    {
        $task = $this->runningTask($taskKey, $revision);
        $execution = $this->execution($taskKey);
        if ((string)$execution['current_step'] !== 'execution') {
            throw new \RuntimeException('OPS_MODULE_STEP_INVALID');
        }
        $payload = $this->payload($task);
        $result = $this->requestStore()->execute($payload['request_key']);
        $expected = match ($payload['operation']) {
            'update' => ['upgraded', 'unchanged'],
            'retire' => ['retired', 'unchanged'],
            'purge' => ['purged', 'unchanged'],
            default => [],
        };
        if (!in_array((string)($result['operation'] ?? ''), $expected, true)
            || !hash_equals($payload['package_key'], (string)($result['package_key'] ?? ''))
        ) {
            throw new \RuntimeException('OPS_MODULE_EXECUTION_FAILED');
        }
        $json = $this->canonicalJson($result);
        $sha = hash('sha256', $json);
        $this->transaction(function () use ($taskKey, $revision, $json, $sha): void {
            $this->lockedRunningTask($taskKey, $revision);
            $update = $this->pdo->prepare(<<<'SQL'
UPDATE pa_ops_module_execution
SET current_step='smoke', operation_result_json=:result_json,
    operation_result_sha256=:result_sha, updated_at=UTC_TIMESTAMP(3)
WHERE task_key=:task_key AND current_step='execution'
SQL);
            $update->execute(['result_json' => $json, 'result_sha' => $sha, 'task_key' => $taskKey]);
            if ($update->rowCount() !== 1) {
                throw new \RuntimeException('OPS_MODULE_STEP_CONFLICT');
            }
        });
        return ['action' => 'run_smoke', 'result_sha256' => $sha];
    }

    /** @return array<string,mixed> */
    public function succeed(string $taskKey, int $revision): array
    {
        $task = $this->runningTask($taskKey, $revision);
        $payload = $this->payload($task);
        $execution = $this->execution($taskKey);
        if ((string)$execution['current_step'] !== 'smoke') {
            throw new \RuntimeException('OPS_MODULE_STEP_INVALID');
        }
        $result = json_decode((string)$execution['operation_result_json'], true, 128, JSON_THROW_ON_ERROR);
        if (!is_array($result) || !$this->smoke($payload, $result)) {
            throw new \RuntimeException('OPS_MODULE_SMOKE_FAILED');
        }
        return $this->transaction(function () use ($task, $payload, $execution): array {
            $locked = $this->lockedRunningTask((string)$task['task_key'], (int)$task['revision']);
            $current = $this->execution((string)$task['task_key'], true);
            if ((string)$current['current_step'] !== 'smoke') {
                throw new \RuntimeException('OPS_MODULE_STEP_INVALID');
            }
            $pointer = $this->recoveryPointer($payload, $current);
            $pointerJson = $this->canonicalJson($pointer);
            $pointerSha = hash('sha256', $pointerJson);
            $context = $this->context($task);
            $maintenanceKey = (string)$current['maintenance_key'];
            $maintenanceRevision = (int)$current['maintenance_revision'];
            (new PdoMaintenanceWindowStore($this->pdo))->close(
                $context,
                $maintenanceKey,
                $maintenanceRevision,
                hash('sha256', (string)$task['task_key'] . ':maintenance-close'),
                hash('sha256', $maintenanceKey . ':' . $maintenanceRevision),
                new OpsAuditEvent('platform.ops.maintenance.closed', 'maintenance.close', [
                    'maintenance_key' => $maintenanceKey,
                    'revision' => $maintenanceRevision,
                ]),
            );
            $executionUpdate = $this->pdo->prepare(<<<'SQL'
UPDATE pa_ops_module_execution
SET current_step='completed', recovery_pointer_json=:pointer_json,
    recovery_pointer_sha256=:pointer_sha, completed_at=UTC_TIMESTAMP(3),
    updated_at=UTC_TIMESTAMP(3)
WHERE task_key=:task_key AND current_step='smoke'
SQL);
            $executionUpdate->execute([
                'pointer_json' => $pointerJson,
                'pointer_sha' => $pointerSha,
                'task_key' => $task['task_key'],
            ]);
            $taskUpdate = $this->pdo->prepare(<<<'SQL'
UPDATE pa_ops_task
SET status='succeeded', revision=revision+1, last_error_code=NULL,
    completed_at=UTC_TIMESTAMP(3), updated_at=UTC_TIMESTAMP(3)
WHERE id=:id AND status='running' AND revision=:revision
SQL);
            $taskUpdate->execute(['id' => $locked['id'], 'revision' => $locked['revision']]);
            if ($executionUpdate->rowCount() !== 1 || $taskUpdate->rowCount() !== 1) {
                throw new \RuntimeException('OPS_MODULE_FINALIZE_CONFLICT');
            }
            $this->audit($task, 'platform.ops.module.succeeded', 'module.succeed', [
                'task_key' => $task['task_key'],
                'request_key' => $payload['request_key'],
                'environment' => $payload['environment'],
                'target_resource_id' => $payload['target_resource_id'],
                'package_key' => $payload['package_key'],
                'operation' => $payload['operation'],
                'recovery_pointer_sha256' => $pointerSha,
            ], AuditOutcome::Success, null);
            return [
                'task_key' => (string)$task['task_key'],
                'status' => 'succeeded',
                'operation' => $payload['operation'],
                'package_key' => $payload['package_key'],
                'recovery_pointer_sha256' => $pointerSha,
            ];
        });
    }

    /** @return array{task_key:string,status:string,error_code:string,recovery_pointer_sha256:?string} */
    public function fail(string $taskKey, int $revision, string $errorCode): array
    {
        if (!in_array($errorCode, self::FAILURE_CODES, true)) {
            throw new \InvalidArgumentException('OPS_MODULE_FAILURE_CODE_INVALID');
        }
        return $this->transaction(function () use ($taskKey, $revision, $errorCode): array {
            $task = $this->lockedRunningTask($taskKey, $revision);
            $payload = $this->payload($task);
            $execution = $this->execution($taskKey, true);
            $pointerSha = null;
            if ($execution['backup_reference_key'] !== null) {
                $pointer = $this->recoveryPointer($payload, $execution) + [
                    'failed_step' => (string)$execution['current_step'],
                    'error_code' => $errorCode,
                ];
                $pointerJson = $this->canonicalJson($pointer);
                $pointerSha = hash('sha256', $pointerJson);
                $store = $this->pdo->prepare(<<<'SQL'
UPDATE pa_ops_module_execution
SET recovery_pointer_json=:pointer_json, recovery_pointer_sha256=:pointer_sha,
    updated_at=UTC_TIMESTAMP(3)
WHERE task_key=:task_key
SQL);
                $store->execute(['pointer_json' => $pointerJson, 'pointer_sha' => $pointerSha, 'task_key' => $taskKey]);
            }
            $update = $this->pdo->prepare(<<<'SQL'
UPDATE pa_ops_task
SET status='dead', revision=revision+1, last_error_code=:error_code,
    completed_at=UTC_TIMESTAMP(3), updated_at=UTC_TIMESTAMP(3)
WHERE id=:id AND status='running' AND revision=:revision
SQL);
            $update->execute(['error_code' => $errorCode, 'id' => $task['id'], 'revision' => $revision]);
            if ($update->rowCount() !== 1) {
                throw new \RuntimeException('OPS_MODULE_FINALIZE_CONFLICT');
            }
            $this->audit($task, 'platform.ops.module.failed', 'module.fail', [
                'task_key' => $taskKey,
                'request_key' => $payload['request_key'],
                'environment' => $payload['environment'],
                'target_resource_id' => $payload['target_resource_id'],
                'package_key' => $payload['package_key'],
                'operation' => $payload['operation'],
                'failed_step' => $execution['current_step'],
                'error_code' => $errorCode,
                'recovery_pointer_sha256' => $pointerSha,
            ], AuditOutcome::Error, $errorCode);
            return [
                'task_key' => $taskKey,
                'status' => 'dead',
                'error_code' => $errorCode,
                'recovery_pointer_sha256' => $pointerSha,
            ];
        });
    }

    /** @return array{task_key:string,status:string} */
    public function heartbeat(string $taskKey, int $revision): array
    {
        $statement = $this->pdo->prepare(<<<'SQL'
UPDATE pa_ops_task SET updated_at=UTC_TIMESTAMP(3)
WHERE task_key=:task_key AND task_type=:task_type AND status='running' AND revision=:revision
SQL);
        $statement->execute([
            'task_key' => $taskKey,
            'task_type' => PlatformModuleOperationExecutionService::TASK_TYPE,
            'revision' => $revision,
        ]);
        if ($statement->rowCount() !== 1) {
            throw new \RuntimeException('OPS_MODULE_EXECUTION_FENCED');
        }
        return ['task_key' => $taskKey, 'status' => 'running'];
    }

    /** @param array<string,mixed> $task @return array<string,mixed> */
    private function advancePreflight(array $task, int $revision): array
    {
        $payload = $this->payload($task);
        $request = $this->requestStore()->assertPrepared($payload['request_key']);
        $runtime = $this->runtime($this->context($task));
        if (!hash_equals($payload['request_sha256'], (string)$request['request_sha256'])
            || !hash_equals($payload['source_commit'], $runtime['commit'])
            || !hash_equals($payload['source_tree'], $runtime['tree'])
            || $runtime['health'] === 'unhealthy'
        ) {
            throw new \RuntimeException('OPS_MODULE_PREFLIGHT_FAILED');
        }
        $this->requestStore()->preview(
            $payload['delivery_resource_id'],
            $payload['target_resource_id'],
            $payload['operation'],
            $payload['package_key'],
            $payload['archive_sha256'] === '' ? null : $payload['archive_sha256'],
            $payload['signature_key_id'] === '' ? null : $payload['signature_key_id'],
        );
        return $this->transaction(function () use ($task, $revision): array {
            $this->lockedRunningTask((string)$task['task_key'], $revision);
            $execution = $this->execution((string)$task['task_key'], true);
            if ((string)$execution['current_step'] !== 'preflight') {
                throw new \RuntimeException('OPS_MODULE_STEP_INVALID');
            }
            $context = $this->context($task);
            $provider = $this->backupProviders->require(PairedBackupProvider::PROVIDER_KEY);
            $child = (new PdoOpsTaskDispatcher($this->pdo))->dispatch($context, $this->childSubmission(
                Package::BACKUP_TASK_TYPE,
                $provider->backupHandlerKey,
                ['provider_key' => $provider->key],
                Package::BACKUP_TASK_TYPE . '.' . $provider->key,
                $provider->maximumAttempts,
                (string)$task['task_key'] . ':backup',
                'platform.ops.backup.submitted',
                'backup.submit',
            ));
            $update = $this->pdo->prepare(<<<'SQL'
UPDATE pa_ops_module_execution
SET current_step='backup', backup_task_key=:child, updated_at=UTC_TIMESTAMP(3)
WHERE task_key=:task_key AND current_step='preflight'
SQL);
            $update->execute(['child' => $child->taskKey, 'task_key' => $task['task_key']]);
            if ($update->rowCount() !== 1) {
                throw new \RuntimeException('OPS_MODULE_STEP_CONFLICT');
            }
            return ['action' => 'run_backup', 'child_task_key' => $child->taskKey];
        });
    }

    /** @param array<string,mixed> $task @return array<string,mixed> */
    private function advanceBackup(array $task, int $revision): array
    {
        return $this->transaction(function () use ($task, $revision): array {
            $this->lockedRunningTask((string)$task['task_key'], $revision);
            $execution = $this->execution((string)$task['task_key'], true);
            $child = $this->one('SELECT * FROM pa_ops_task WHERE task_key=:key FOR UPDATE', ['key' => $execution['backup_task_key']]);
            if ($child === null || (string)$child['status'] !== 'succeeded') {
                if ($child !== null && in_array((string)$child['status'], ['queued', 'running'], true)) {
                    return ['action' => 'wait_backup', 'child_task_key' => (string)$child['task_key']];
                }
                throw new \RuntimeException('OPS_MODULE_BACKUP_FAILED');
            }
            $evidence = $this->one('SELECT * FROM pa_ops_backup_evidence WHERE task_key=:key FOR UPDATE', ['key' => $child['task_key']]);
            $payload = $this->payload($task);
            if ($evidence === null
                || !hash_equals($payload['source_commit'], (string)$evidence['source_commit'])
                || !hash_equals($payload['source_tree'], (string)$evidence['source_tree'])
            ) {
                throw new \RuntimeException('OPS_MODULE_BACKUP_FAILED');
            }
            $provider = $this->backupProviders->require(PairedBackupProvider::PROVIDER_KEY);
            $restore = (new PdoOpsTaskDispatcher($this->pdo))->dispatch($this->context($task), $this->childSubmission(
                Package::RESTORE_TASK_TYPE,
                $provider->restoreHandlerKey,
                [
                    'provider_key' => $provider->key,
                    'backup_reference_key' => (string)$evidence['backup_reference_key'],
                    'target_key' => PairedBackupProvider::RESTORE_TARGET_KEY,
                ],
                Package::RESTORE_TASK_TYPE . '.' . $provider->key,
                $provider->maximumAttempts,
                (string)$task['task_key'] . ':restore',
                'platform.ops.restore.submitted',
                'restore.submit',
            ));
            $update = $this->pdo->prepare(<<<'SQL'
UPDATE pa_ops_module_execution
SET current_step='restore_verification', backup_reference_key=:backup,
    restore_task_key=:restore, updated_at=UTC_TIMESTAMP(3)
WHERE task_key=:task_key AND current_step='backup'
SQL);
            $update->execute([
                'backup' => $evidence['backup_reference_key'],
                'restore' => $restore->taskKey,
                'task_key' => $task['task_key'],
            ]);
            if ($update->rowCount() !== 1) {
                throw new \RuntimeException('OPS_MODULE_STEP_CONFLICT');
            }
            return ['action' => 'run_restore', 'child_task_key' => $restore->taskKey];
        });
    }

    /** @param array<string,mixed> $task @return array<string,mixed> */
    private function advanceRestore(array $task, int $revision): array
    {
        $result = $this->transaction(function () use ($task, $revision): array {
            $this->lockedRunningTask((string)$task['task_key'], $revision);
            $execution = $this->execution((string)$task['task_key'], true);
            $child = $this->one('SELECT * FROM pa_ops_task WHERE task_key=:key FOR UPDATE', ['key' => $execution['restore_task_key']]);
            if ($child === null || (string)$child['status'] !== 'succeeded') {
                if ($child !== null && in_array((string)$child['status'], ['queued', 'running'], true)) {
                    return ['action' => 'wait_restore', 'child_task_key' => (string)$child['task_key']];
                }
                throw new \RuntimeException('OPS_MODULE_RESTORE_FAILED');
            }
            $evidence = $this->one('SELECT * FROM pa_ops_restore_evidence WHERE task_key=:key FOR UPDATE', ['key' => $child['task_key']]);
            $payload = $this->payload($task);
            if ($evidence === null
                || !hash_equals((string)$execution['backup_reference_key'], (string)$evidence['backup_reference_key'])
                || !hash_equals($payload['source_commit'], (string)$evidence['source_commit'])
                || !hash_equals($payload['source_tree'], (string)$evidence['source_tree'])
            ) {
                throw new \RuntimeException('OPS_MODULE_RESTORE_FAILED');
            }
            $update = $this->pdo->prepare(<<<'SQL'
UPDATE pa_ops_module_execution
SET current_step='maintenance', restore_evidence_sha256=:sha, updated_at=UTC_TIMESTAMP(3)
WHERE task_key=:task_key AND current_step='restore_verification'
SQL);
            $update->execute(['sha' => $evidence['evidence_sha256'], 'task_key' => $task['task_key']]);
            if ($update->rowCount() !== 1) {
                throw new \RuntimeException('OPS_MODULE_STEP_CONFLICT');
            }
            return ['action' => 'begin_maintenance'];
        });
        return ($result['action'] ?? null) === 'begin_maintenance'
            ? $this->advanceMaintenance($task, $revision) : $result;
    }

    /** @param array<string,mixed> $task @return array<string,mixed> */
    private function advanceMaintenance(array $task, int $revision): array
    {
        return $this->transaction(function () use ($task, $revision): array {
            $this->lockedRunningTask((string)$task['task_key'], $revision);
            $execution = $this->execution((string)$task['task_key'], true);
            if ((string)$execution['current_step'] !== 'maintenance'
                || $execution['maintenance_key'] !== null
            ) {
                throw new \RuntimeException('OPS_MODULE_STEP_INVALID');
            }
            $context = $this->context($task);
            $key = 'maintenance_' . bin2hex(random_bytes(16));
            $now = new DateTimeImmutable('now', new DateTimeZone('UTC'));
            $window = new MaintenanceWindow(
                $key,
                'active',
                'module-lifecycle',
                $now->modify('-1 minute')->format('Y-m-d\TH:i:s.v\Z'),
                $now->modify('+23 hours')->format('Y-m-d\TH:i:s.v\Z'),
                1,
            );
            $idempotencyDigest = hash('sha256', (string)$task['task_key'] . ':maintenance-open');
            $requestDigest = hash('sha256', $key . ':' . $window->startsAt . ':' . $window->endsAt);
            $created = (new PdoMaintenanceWindowStore($this->pdo))->schedule(
                $context,
                $window,
                0,
                $idempotencyDigest,
                $requestDigest,
                new OpsAuditEvent('platform.ops.maintenance.scheduled', 'maintenance.schedule', [
                    'maintenance_key' => $key,
                    'revision' => 1,
                    'idempotency_digest' => $idempotencyDigest,
                    'request_digest' => $requestDigest,
                ]),
            );
            $update = $this->pdo->prepare(<<<'SQL'
UPDATE pa_ops_module_execution
SET current_step='execution', maintenance_key=:key, maintenance_revision=:revision,
    updated_at=UTC_TIMESTAMP(3)
WHERE task_key=:task_key AND current_step='maintenance'
SQL);
            $update->execute([
                'key' => $created->maintenanceKey,
                'revision' => $created->revision,
                'task_key' => $task['task_key'],
            ]);
            if ($update->rowCount() !== 1) {
                throw new \RuntimeException('OPS_MODULE_STEP_CONFLICT');
            }
            return ['action' => 'execute'];
        });
    }

    /** @param array<string,string> $payload @param array<string,mixed> $result */
    private function smoke(array $payload, array $result): bool
    {
        if (!hash_equals($payload['package_key'], (string)($result['package_key'] ?? ''))) {
            return false;
        }
        $statement = $this->pdo->prepare(
            'SELECT status,installed_version,artifact_sha256,lock_digest FROM pa_plugin_installation WHERE plugin_key=?'
        );
        $statement->execute([$payload['package_key']]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        if ($payload['operation'] === 'update') {
            return is_array($row)
                && (string)$row['status'] === 'active'
                && hash_equals((string)($result['version'] ?? ''), (string)$row['installed_version'])
                && hash_equals((string)($result['artifact_sha256'] ?? ''), (string)$row['artifact_sha256'])
                && hash_equals((string)($result['lock_digest'] ?? ''), (string)$row['lock_digest']);
        }
        if ($payload['operation'] === 'retire') {
            return is_array($row) && (string)$row['status'] === 'uninstalled';
        }
        if ($row !== false) {
            return false;
        }
        $modules = $result['affected_modules'] ?? [];
        if (!is_array($modules) || $modules === []) {
            return ($result['operation'] ?? null) === 'unchanged';
        }
        $keys = array_values(array_filter(array_column($modules, 'module_key'), 'is_string'));
        if (count($keys) !== count($modules)) {
            return false;
        }
        $check = $this->pdo->prepare(
            'SELECT COUNT(*) FROM pa_module_installation WHERE module_key IN ('
            . implode(',', array_fill(0, count($keys), '?')) . ')'
        );
        $check->execute($keys);
        return (int)$check->fetchColumn() === 0;
    }

    /** @param array<string,string> $payload @param array<string,mixed> $execution @return array<string,mixed> */
    private function recoveryPointer(array $payload, array $execution): array
    {
        if (!is_string($execution['backup_reference_key'] ?? null)
            || !is_string($execution['restore_evidence_sha256'] ?? null)
        ) {
            throw new \RuntimeException('OPS_MODULE_RECOVERY_POINTER_FAILED');
        }
        $backup = $this->one(
            'SELECT provider_key,manifest_sha256 FROM pa_ops_backup_evidence WHERE backup_reference_key=:reference',
            ['reference' => $execution['backup_reference_key']],
        );
        if ($backup === null) {
            throw new \RuntimeException('OPS_MODULE_RECOVERY_POINTER_FAILED');
        }
        return [
            'schema_version' => 1,
            'request_key' => $payload['request_key'],
            'request_sha256' => $payload['request_sha256'],
            'environment' => $payload['environment'],
            'target_resource_id' => $payload['target_resource_id'],
            'package_key' => $payload['package_key'],
            'operation' => $payload['operation'],
            'source_commit' => $payload['source_commit'],
            'source_tree' => $payload['source_tree'],
            'provider_key' => (string)$backup['provider_key'],
            'backup_reference_key' => (string)$execution['backup_reference_key'],
            'backup_manifest_sha256' => (string)$backup['manifest_sha256'],
            'restore_evidence_sha256' => (string)$execution['restore_evidence_sha256'],
            'operation_result_sha256' => $execution['operation_result_sha256'] === null
                ? null : (string)$execution['operation_result_sha256'],
        ];
    }

    private function requestStore(): DeploymentModuleRequestService
    {
        return new DeploymentModuleRequestService(
            $this->pdo,
            $this->projectRoot,
            $this->moduleConfig,
            $this->trustedKeys,
            $this->registryPath,
        );
    }

    /** @return array{commit:string,tree:string,health:string,repository_clean:bool} */
    private function runtime(PlatformContext $context): array
    {
        if ($this->runtimeStatus instanceof Closure) {
            $identity = ($this->runtimeStatus)($context);
            if (is_array($identity)) return $identity;
        }
        $snapshot = $this->runtimeStatus->snapshot($context);
        return [
            'commit' => $snapshot->commit,
            'tree' => $snapshot->tree,
            'health' => $snapshot->health,
            'repository_clean' => $snapshot->repositoryClean,
        ];
    }


    private function failStaleRunningTasks(): void
    {
        $statement = $this->pdo->query(<<<'SQL'
SELECT task.task_key
FROM pa_ops_task task
JOIN pa_ops_module_execution execution ON execution.task_key=task.task_key
WHERE task.task_type='ops.module.execute' AND task.status='running'
  AND task.updated_at<UTC_TIMESTAMP(3)-INTERVAL 2 HOUR
FOR UPDATE
SQL);
        while ($statement !== false && ($row = $statement->fetch(PDO::FETCH_ASSOC)) !== false) {
            $update = $this->pdo->prepare(<<<'SQL'
UPDATE pa_ops_task
SET status='dead', revision=revision+1, last_error_code='OPS_MODULE_WORKER_STALE',
    completed_at=UTC_TIMESTAMP(3), updated_at=UTC_TIMESTAMP(3)
WHERE task_key=:task_key AND status='running'
SQL);
            $update->execute(['task_key' => $row['task_key']]);
        }
    }

    private function childSubmission(
        string $taskType,
        string $handlerKey,
        array $payload,
        string $concurrencyKey,
        int $maximumAttempts,
        string $idempotencyKey,
        string $eventType,
        string $action,
    ): OpsTaskSubmission {
        $idempotencyDigest = hash('sha256', $idempotencyKey);
        $requestDigest = hash('sha256', $this->canonicalJson(['task_type' => $taskType, 'payload' => $payload]));
        return new OpsTaskSubmission(
            $taskType,
            $handlerKey,
            $payload,
            $idempotencyDigest,
            $requestDigest,
            $concurrencyKey,
            $maximumAttempts,
            new OpsAuditEvent($eventType, $action, [
                'provider_key' => $payload['provider_key'],
                'target_key' => $payload['target_key'] ?? null,
                'idempotency_digest' => $idempotencyDigest,
                'request_digest' => $requestDigest,
            ]),
        );
    }

    /** @param array<string,mixed> $task @return array<string,string> */
    private function payload(array $task): array
    {
        $payload = json_decode((string)$task['payload_json'], true, 64, JSON_THROW_ON_ERROR);
        $expected = [
            'archive_sha256', 'confirm_plan_json', 'confirm_plan_sha256',
            'delivery_resource_id', 'environment', 'operation', 'package_key',
            'request_key', 'request_sha256', 'signature_key_id', 'source_commit',
            'source_tree', 'target_resource_id',
        ];
        $keys = is_array($payload) ? array_keys($payload) : [];
        sort($keys, SORT_STRING);
        if ($keys !== $expected) {
            throw new \RuntimeException('OPS_MODULE_PAYLOAD_INVALID');
        }
        foreach ($payload as $value) {
            if (!is_string($value)) {
                throw new \RuntimeException('OPS_MODULE_PAYLOAD_INVALID');
            }
        }
        if (preg_match('/^modreq_[a-f0-9]{32}$/D', $payload['request_key']) !== 1
            || preg_match('/^[a-f0-9]{64}$/D', $payload['request_sha256']) !== 1
            || preg_match('/^[a-f0-9]{40}$/D', $payload['source_commit']) !== 1
            || preg_match('/^[a-f0-9]{40}$/D', $payload['source_tree']) !== 1
            || !in_array($payload['operation'], ['update', 'retire', 'purge'], true)
        ) {
            throw new \RuntimeException('OPS_MODULE_PAYLOAD_INVALID');
        }
        return $payload;
    }

    /** @param array<string,mixed> $task */
    private function context(array $task): PlatformContext
    {
        return PlatformContext::fromValidatedSession(
            new ValidatedPlatformSession(
                1,
                'module-worker-' . substr((string)$task['task_key'], 4, 16),
                (int)$task['account_id'],
                (int)$task['submitted_by_operator_id'],
                'platform-web',
                new DateTimeImmutable('now'),
            ),
            'module-' . substr((string)$task['task_key'], 4),
        );
    }

    /** @return array<string,mixed> */
    private function runningTask(string $taskKey, int $revision): array
    {
        $task = $this->one(<<<'SQL'
SELECT task.*,operator.account_id FROM pa_ops_task task
JOIN pa_platform_operator operator ON operator.id=task.submitted_by_operator_id
WHERE task.task_key=:task_key AND task.task_type=:task_type
  AND task.status='running' AND task.revision=:revision
SQL, ['task_key' => $taskKey, 'task_type' => PlatformModuleOperationExecutionService::TASK_TYPE, 'revision' => $revision]);
        if ($task === null) {
            throw new \RuntimeException('OPS_MODULE_EXECUTION_FENCED');
        }
        return $task;
    }

    /** @return array<string,mixed> */
    private function lockedRunningTask(string $taskKey, int $revision): array
    {
        $task = $this->one(<<<'SQL'
SELECT task.*,operator.account_id FROM pa_ops_task task
JOIN pa_platform_operator operator ON operator.id=task.submitted_by_operator_id
WHERE task.task_key=:task_key AND task.task_type=:task_type
  AND task.status='running' AND task.revision=:revision FOR UPDATE
SQL, ['task_key' => $taskKey, 'task_type' => PlatformModuleOperationExecutionService::TASK_TYPE, 'revision' => $revision]);
        if ($task === null) {
            throw new \RuntimeException('OPS_MODULE_EXECUTION_FENCED');
        }
        return $task;
    }

    /** @return array<string,mixed> */
    private function execution(string $taskKey, bool $forUpdate = false): array
    {
        $row = $this->one(
            'SELECT * FROM pa_ops_module_execution WHERE task_key=:task_key' . ($forUpdate ? ' FOR UPDATE' : ''),
            ['task_key' => $taskKey],
        );
        if ($row === null) {
            throw new \RuntimeException('OPS_MODULE_EXECUTION_UNAVAILABLE');
        }
        return $row;
    }

    /** @param array<string,mixed> $task @param array<string,mixed> $metadata */
    private function audit(
        array $task,
        string $eventType,
        string $action,
        array $metadata,
        AuditOutcome $outcome,
        ?string $reasonCode,
    ): void
    {
        AuditContractHost::fromPdo($this->pdo)->recordPlatform(
            $eventType,
            $action,
            'module-' . substr((string)$task['task_key'], 4),
            (int)$task['submitted_by_operator_id'],
            (int)$task['account_id'],
            $metadata,
            $outcome,
            $reasonCode,
        );
    }

    /** @param array<string,mixed> $parameters @return array<string,mixed>|null */
    private function one(string $sql, array $parameters): ?array
    {
        $statement = $this->pdo->prepare($sql);
        $statement->execute($parameters);
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        return is_array($row) ? $row : null;
    }

    private function canonicalJson(array $value): string
    {
        $normalize = function (mixed $item) use (&$normalize): mixed {
            if (!is_array($item)) return $item;
            if (!array_is_list($item)) ksort($item, SORT_STRING);
            foreach ($item as $key => $child) $item[$key] = $normalize($child);
            return $item;
        };
        return json_encode($normalize($value), JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
    }

    private function transaction(callable $operation): mixed
    {
        $owns = !$this->pdo->inTransaction();
        if ($owns) $this->pdo->beginTransaction();
        try {
            $result = $operation();
            if ($owns) $this->pdo->commit();
            return $result;
        } catch (Throwable $exception) {
            if ($owns && $this->pdo->inTransaction()) $this->pdo->rollBack();
            throw $exception;
        }
    }
}
