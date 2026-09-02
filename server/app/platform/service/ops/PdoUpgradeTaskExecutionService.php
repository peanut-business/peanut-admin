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
use PeanutAdmin\OpsConsole\Application\OpsConsoleException;
use PeanutAdmin\OpsConsole\Maintenance\MaintenanceWindow;
use PeanutAdmin\OpsConsole\Package;
use PeanutAdmin\OpsConsole\Task\OpsAuditEvent;
use PeanutAdmin\OpsConsole\Task\OpsTaskSubmission;
use PeanutAdmin\OpsConsole\Task\BackupRestoreProviderRegistry;
use Throwable;

/** Trusted state machine behind the fixed deployment-control worker. */
final readonly class PdoUpgradeTaskExecutionService
{
    private const STEPS = [
        1 => 'preflight',
        2 => 'backup',
        3 => 'restore_verification',
        4 => 'maintenance',
        5 => 'deployment',
        6 => 'smoke',
        7 => 'recovery_pointer',
    ];
    private const FAILURE_CODES = [
        'OPS_UPGRADE_PREFLIGHT_FAILED',
        'OPS_UPGRADE_BACKUP_FAILED',
        'OPS_UPGRADE_RESTORE_FAILED',
        'OPS_UPGRADE_MAINTENANCE_FAILED',
        'OPS_UPGRADE_DEPLOYMENT_FAILED',
        'OPS_UPGRADE_SMOKE_FAILED',
        'OPS_UPGRADE_RECOVERY_POINTER_FAILED',
        'OPS_UPGRADE_WORKER_STALE',
        'OPS_UPGRADE_WORKER_FAILED',
    ];

    public function __construct(
        private PDO $pdo,
        private AuditContractHost $audit,
        private string $projectRoot,
        private BackupRestoreProviderRegistry $backupProviders,
        private ApplicationRuntimeStatusProvider $runtimeStatus,
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
JOIN pa_platform_operator operator ON operator.id = task.submitted_by_operator_id
WHERE task.task_type = :task_type
  AND task.handler_key = :handler_key
  AND task.status = 'queued'
  AND task.available_at <= UTC_TIMESTAMP(3)
ORDER BY task.id
LIMIT 1
FOR UPDATE
SQL, [
                'task_type' => PlatformUpgradeExecutionService::TASK_TYPE,
                'handler_key' => PlatformUpgradeExecutionService::HANDLER_KEY,
            ]);
            if ($task === null) {
                return null;
            }
            $payload = $this->payload($task);
            $update = $this->pdo->prepare(<<<'SQL'
UPDATE pa_ops_task
SET status = 'running', attempt_count = attempt_count + 1,
    revision = revision + 1, updated_at = UTC_TIMESTAMP(3)
WHERE id = :id AND status = 'queued' AND revision = :revision
SQL);
            $update->execute(['id' => $task['id'], 'revision' => $task['revision']]);
            if ($update->rowCount() !== 1) {
                throw new \RuntimeException('OPS_UPGRADE_CLAIM_CONFLICT');
            }
            $revision = (int)$task['revision'] + 1;
            $this->createExecution((string)$task['task_key'], $payload);
            $this->startStep((string)$task['task_key'], 'preflight');
            $this->audit($task, 'platform.ops.upgrade.claimed', 'upgrade.claim', [
                'task_key' => $task['task_key'],
                'target_release_key' => $payload['target_release_key'],
                'execution_revision' => $revision,
            ], AuditOutcome::Success, null);
            return [
                'task_key' => (string)$task['task_key'],
                'execution_revision' => $revision,
                'current_step' => 'preflight',
                'target_release_key' => $payload['target_release_key'],
                'target_descriptor_sha256' => $payload['target_descriptor_sha256'],
            ];
        });
    }

    /** @return array<string,mixed> */
    public function advance(string $taskKey, int $revision): array
    {
        $task = $this->runningTask($taskKey, $revision);
        $execution = $this->execution($taskKey);
        return match ((string)$execution['current_step']) {
            'preflight' => $this->advancePreflight($task, $revision),
            'backup' => $this->advanceBackup($task, $revision),
            'restore_verification' => $this->advanceRestore($task, $revision),
            'maintenance' => $this->advanceMaintenance($task, $revision),
            'deployment' => [
                'action' => 'deploy',
                'target_release_key' => (string)$execution['target_release_key'],
            ],
            default => throw new \RuntimeException('OPS_UPGRADE_STEP_INVALID'),
        };
    }

    /** @return array<string,mixed> */
    public function succeed(string $taskKey, int $revision): array
    {
        $task = $this->runningTask($taskKey, $revision);
        $execution = $this->execution($taskKey);
        if ((string)$execution['current_step'] !== 'deployment') {
            throw new \RuntimeException('OPS_UPGRADE_STEP_INVALID');
        }
        $this->transaction(function () use ($task, $execution): void {
            $this->lockedRunningTask((string)$task['task_key'], (int)$task['revision']);
            $lockedExecution = $this->execution((string)$task['task_key'], true);
            if ((string)$lockedExecution['current_step'] !== 'deployment') {
                throw new \RuntimeException('OPS_UPGRADE_STEP_INVALID');
            }
            $this->succeedStep(
                (string)$task['task_key'],
                'deployment',
                $this->digest([
                    'release_key' => $execution['target_release_key'],
                    'commit' => $execution['target_commit'],
                    'tree' => $execution['target_tree'],
                ])
            );
            $this->startStep((string)$task['task_key'], 'smoke');
            $move = $this->pdo->prepare(<<<'SQL'
UPDATE pa_ops_upgrade_execution
SET current_step = 'smoke', updated_at = UTC_TIMESTAMP(3)
WHERE task_key = :task_key AND current_step = 'deployment'
SQL);
            $move->execute(['task_key' => $task['task_key']]);
            if ($move->rowCount() !== 1) {
                throw new \RuntimeException('OPS_UPGRADE_STEP_CONFLICT');
            }
        });
        $context = $this->context($task);
        $snapshot = $this->runtimeStatus->snapshot($context);
        if (!hash_equals((string)$execution['target_commit'], $snapshot->commit)
            || !hash_equals((string)$execution['target_tree'], $snapshot->tree)
            || $snapshot->releaseKey !== (string)$execution['target_release_key']
            || $snapshot->health === 'unhealthy'
            || $snapshot->pendingMigrations !== 0
            || $snapshot->migrationDrift
            || !$snapshot->repositoryClean
        ) {
            throw new \RuntimeException('OPS_UPGRADE_SMOKE_FAILED');
        }

        $this->transaction(function () use ($task, $snapshot): void {
            $locked = $this->lockedRunningTask((string)$task['task_key'], (int)$task['revision']);
            $lockedExecution = $this->execution((string)$task['task_key'], true);
            if ((string)$lockedExecution['current_step'] !== 'smoke') {
                throw new \RuntimeException('OPS_UPGRADE_STEP_INVALID');
            }
            $smokeOutput = $this->digest([
                'health' => $snapshot->health,
                'migration_digest' => $snapshot->migrationDigest,
                'checks' => $snapshot->checks,
            ]);
            $this->succeedStep((string)$task['task_key'], 'smoke', $smokeOutput);
            $this->startStep((string)$task['task_key'], 'recovery_pointer');

            $move = $this->pdo->prepare(<<<'SQL'
UPDATE pa_ops_upgrade_execution
SET current_step = 'recovery_pointer', updated_at = UTC_TIMESTAMP(3)
WHERE task_key = :task_key AND current_step = 'smoke'
SQL);
            $move->execute(['task_key' => $task['task_key']]);
            if ($move->rowCount() !== 1 || (string)$locked['task_key'] !== (string)$task['task_key']) {
                throw new \RuntimeException('OPS_UPGRADE_STEP_CONFLICT');
            }
        });

        return $this->transaction(function () use ($task, $context): array {
            $locked = $this->lockedRunningTask((string)$task['task_key'], (int)$task['revision']);
            $lockedExecution = $this->execution((string)$task['task_key'], true);
            if ((string)$lockedExecution['current_step'] !== 'recovery_pointer') {
                throw new \RuntimeException('OPS_UPGRADE_STEP_INVALID');
            }

            $backup = $this->one(
                'SELECT provider_key, backup_reference_key, manifest_sha256 FROM pa_ops_backup_evidence WHERE backup_reference_key = :reference',
                ['reference' => $lockedExecution['backup_reference_key']]
            );
            $restore = $this->one(
                'SELECT target_key, evidence_sha256, verified_at FROM pa_ops_restore_evidence WHERE task_key = :task_key',
                ['task_key' => $lockedExecution['restore_task_key']]
            );
            if ($backup === null || $restore === null) {
                throw new \RuntimeException('OPS_UPGRADE_RECOVERY_POINTER_FAILED');
            }
            $pointer = [
                'provider_key' => (string)$backup['provider_key'],
                'backup_reference_key' => (string)$backup['backup_reference_key'],
                'manifest_sha256' => (string)$backup['manifest_sha256'],
                'restore_target_key' => (string)$restore['target_key'],
                'restore_verification_sha256' => (string)$restore['evidence_sha256'],
                'restore_verified_at' => $this->instant((string)$restore['verified_at']),
                'source_commit' => (string)$lockedExecution['source_commit'],
                'target_commit' => (string)$lockedExecution['target_commit'],
                'target_release_key' => (string)$lockedExecution['target_release_key'],
            ];
            $pointerJson = $this->canonicalJson($pointer);
            $pointerSha = hash('sha256', $pointerJson);

            $store = new PdoMaintenanceWindowStore($this->pdo, $this->audit);
            $maintenanceKey = (string)$lockedExecution['maintenance_key'];
            $maintenanceRevision = (int)$lockedExecution['maintenance_revision'];
            $idempotencyDigest = hash('sha256', (string)$task['task_key'] . ':maintenance-close');
            $requestDigest = hash('sha256', $maintenanceKey . ':' . $maintenanceRevision);
            $store->close(
                $context,
                $maintenanceKey,
                $maintenanceRevision,
                $idempotencyDigest,
                $requestDigest,
                new OpsAuditEvent('platform.ops.maintenance.closed', 'maintenance.close', [
                    'maintenance_key' => $maintenanceKey,
                    'revision' => $maintenanceRevision,
                    'idempotency_digest' => $idempotencyDigest,
                    'request_digest' => $requestDigest,
                ]),
            );

            $this->succeedStep((string)$task['task_key'], 'recovery_pointer', $pointerSha);
            $updateExecution = $this->pdo->prepare(<<<'SQL'
UPDATE pa_ops_upgrade_execution
SET current_step = 'completed', recovery_pointer_json = :pointer_json,
    recovery_pointer_sha256 = :pointer_sha, completed_at = UTC_TIMESTAMP(3),
    updated_at = UTC_TIMESTAMP(3)
WHERE task_key = :task_key AND current_step = 'recovery_pointer'
SQL);
            $updateExecution->execute([
                'pointer_json' => $pointerJson,
                'pointer_sha' => $pointerSha,
                'task_key' => $task['task_key'],
            ]);
            $updateTask = $this->pdo->prepare(<<<'SQL'
UPDATE pa_ops_task
SET status = 'succeeded', revision = revision + 1, last_error_code = NULL,
    updated_at = UTC_TIMESTAMP(3), completed_at = UTC_TIMESTAMP(3)
WHERE id = :id AND status = 'running' AND revision = :revision
SQL);
            $updateTask->execute(['id' => $locked['id'], 'revision' => $locked['revision']]);
            if ($updateExecution->rowCount() !== 1 || $updateTask->rowCount() !== 1) {
                throw new \RuntimeException('OPS_UPGRADE_FINALIZE_CONFLICT');
            }
            $this->audit($task, 'platform.ops.upgrade.succeeded', 'upgrade.succeed', [
                'task_key' => $task['task_key'],
                'target_release_key' => $lockedExecution['target_release_key'],
                'recovery_pointer_sha256' => $pointerSha,
            ], AuditOutcome::Success, null);
            return [
                'task_key' => (string)$task['task_key'],
                'status' => 'succeeded',
                'target_release_key' => (string)$lockedExecution['target_release_key'],
                'recovery_pointer_sha256' => $pointerSha,
            ];
        });
    }

    /** @return array{task_key:string,status:string,error_code:string} */
    public function fail(string $taskKey, int $revision, string $errorCode): array
    {
        if (!in_array($errorCode, self::FAILURE_CODES, true)) {
            throw new \InvalidArgumentException('OPS_UPGRADE_FAILURE_CODE_INVALID');
        }
        return $this->transaction(function () use ($taskKey, $revision, $errorCode): array {
            $task = $this->lockedRunningTask($taskKey, $revision);
            $execution = $this->execution($taskKey, true);
            $step = (string)$execution['current_step'];
            if ($step === 'completed' || !in_array($step, self::STEPS, true)) {
                throw new \RuntimeException('OPS_UPGRADE_STEP_INVALID');
            }
            $this->failStep($taskKey, $step, $errorCode);
            $update = $this->pdo->prepare(<<<'SQL'
UPDATE pa_ops_task
SET status = 'dead', revision = revision + 1, last_error_code = :error_code,
    updated_at = UTC_TIMESTAMP(3), completed_at = UTC_TIMESTAMP(3)
WHERE id = :id AND status = 'running' AND revision = :revision
SQL);
            $update->execute([
                'error_code' => $errorCode,
                'id' => $task['id'],
                'revision' => $revision,
            ]);
            if ($update->rowCount() !== 1) {
                throw new \RuntimeException('OPS_UPGRADE_FINALIZE_CONFLICT');
            }
            $this->audit($task, 'platform.ops.upgrade.failed', 'upgrade.fail', [
                'task_key' => $taskKey,
                'failed_step' => $step,
                'error_code' => $errorCode,
            ], AuditOutcome::Error, $errorCode);
            return ['task_key' => $taskKey, 'status' => 'dead', 'error_code' => $errorCode];
        });
    }

    /** @return array{task_key:string,status:string} */
    public function heartbeat(string $taskKey, int $revision): array
    {
        $statement = $this->pdo->prepare(<<<'SQL'
UPDATE pa_ops_task
SET updated_at = UTC_TIMESTAMP(3)
WHERE task_key = :task_key AND task_type = :task_type
  AND status = 'running' AND revision = :revision
SQL);
        $statement->execute([
            'task_key' => $taskKey,
            'task_type' => PlatformUpgradeExecutionService::TASK_TYPE,
            'revision' => $revision,
        ]);
        if ($statement->rowCount() !== 1) {
            throw new \RuntimeException('OPS_UPGRADE_EXECUTION_FENCED');
        }
        return ['task_key' => $taskKey, 'status' => 'running'];
    }

    /** @param array<string,mixed> $task @return array<string,mixed> */
    private function advancePreflight(array $task, int $revision): array
    {
        $payload = $this->payload($task);
        $target = PlatformUpgradeTarget::load($this->projectRoot);
        $context = $this->context($task);
        $readiness = $this->runtimeStatus->upgradeReadiness($context);
        if (($readiness['preflight']['state'] ?? null) !== 'ready'
            || !hash_equals($payload['source_commit'], (string)($readiness['source']['runtime']['commit'] ?? ''))
            || !hash_equals($payload['source_tree'], (string)($readiness['source']['runtime']['tree'] ?? ''))
            || !hash_equals($payload['target_commit'], (string)$target->release['commit'])
            || !hash_equals($payload['target_tree'], (string)$target->release['tree'])
            || !hash_equals($payload['target_release_key'], (string)$target->release['key'])
            || !hash_equals($payload['target_descriptor_sha256'], $target->descriptorSha256)
        ) {
            throw new \RuntimeException('OPS_UPGRADE_PREFLIGHT_FAILED');
        }

        return $this->transaction(function () use ($task, $revision, $context, $payload, $readiness): array {
            $this->lockedRunningTask((string)$task['task_key'], $revision);
            $execution = $this->execution((string)$task['task_key'], true);
            if ((string)$execution['current_step'] !== 'preflight') {
                throw new \RuntimeException('OPS_UPGRADE_STEP_INVALID');
            }
            $provider = $this->backupProviders
                ->require(PairedBackupProvider::PROVIDER_KEY);
            $submission = $this->childSubmission(
                Package::BACKUP_TASK_TYPE,
                $provider->backupHandlerKey,
                ['provider_key' => $provider->key],
                Package::BACKUP_TASK_TYPE . '.' . $provider->key,
                $provider->maximumAttempts,
                (string)$task['task_key'] . ':backup',
                'platform.ops.backup.submitted',
                'backup.submit',
            );
            $child = (new PdoOpsTaskDispatcher($this->pdo, $this->audit))->dispatch($context, $submission);
            $this->succeedStep(
                (string)$task['task_key'],
                'preflight',
                $this->digest(['code' => $readiness['preflight']['code'], 'descriptor' => $payload['target_descriptor_sha256']])
            );
            $this->startStep((string)$task['task_key'], 'backup');
            $update = $this->pdo->prepare(<<<'SQL'
UPDATE pa_ops_upgrade_execution
SET current_step = 'backup', backup_task_key = :backup_task_key,
    updated_at = UTC_TIMESTAMP(3)
WHERE task_key = :task_key AND current_step = 'preflight'
SQL);
            $update->execute([
                'backup_task_key' => $child->taskKey,
                'task_key' => $task['task_key'],
            ]);
            if ($update->rowCount() !== 1) {
                throw new \RuntimeException('OPS_UPGRADE_STEP_CONFLICT');
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
            $child = $this->one(
                'SELECT * FROM pa_ops_task WHERE task_key = :task_key FOR UPDATE',
                ['task_key' => $execution['backup_task_key']]
            );
            if ($child === null || (string)$child['status'] !== 'succeeded') {
                if ($child !== null && in_array((string)$child['status'], ['queued', 'running'], true)) {
                    return ['action' => 'wait_backup', 'child_task_key' => (string)$child['task_key']];
                }
                throw new \RuntimeException('OPS_UPGRADE_BACKUP_FAILED');
            }
            $evidence = $this->one(
                'SELECT * FROM pa_ops_backup_evidence WHERE task_key = :task_key FOR UPDATE',
                ['task_key' => $child['task_key']]
            );
            if ($evidence === null
                || !hash_equals((string)$execution['source_commit'], (string)$evidence['source_commit'])
                || !hash_equals((string)$execution['source_tree'], (string)$evidence['source_tree'])
            ) {
                throw new \RuntimeException('OPS_UPGRADE_BACKUP_FAILED');
            }
            $context = $this->context($task);
            $provider = $this->backupProviders
                ->require(PairedBackupProvider::PROVIDER_KEY);
            $payload = [
                'provider_key' => $provider->key,
                'backup_reference_key' => (string)$evidence['backup_reference_key'],
                'target_key' => PairedBackupProvider::RESTORE_TARGET_KEY,
            ];
            $submission = $this->childSubmission(
                Package::RESTORE_TASK_TYPE,
                $provider->restoreHandlerKey,
                $payload,
                Package::RESTORE_TASK_TYPE . '.' . $provider->key,
                $provider->maximumAttempts,
                (string)$task['task_key'] . ':restore',
                'platform.ops.restore.submitted',
                'restore.submit',
            );
            $restore = (new PdoOpsTaskDispatcher($this->pdo, $this->audit))->dispatch($context, $submission);
            $this->succeedStep((string)$task['task_key'], 'backup', (string)$evidence['manifest_sha256']);
            $this->startStep((string)$task['task_key'], 'restore_verification');
            $update = $this->pdo->prepare(<<<'SQL'
UPDATE pa_ops_upgrade_execution
SET current_step = 'restore_verification',
    backup_reference_key = :backup_reference_key,
    restore_task_key = :restore_task_key, updated_at = UTC_TIMESTAMP(3)
WHERE task_key = :task_key AND current_step = 'backup'
SQL);
            $update->execute([
                'backup_reference_key' => $evidence['backup_reference_key'],
                'restore_task_key' => $restore->taskKey,
                'task_key' => $task['task_key'],
            ]);
            if ($update->rowCount() !== 1) {
                throw new \RuntimeException('OPS_UPGRADE_STEP_CONFLICT');
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
            $child = $this->one(
                'SELECT * FROM pa_ops_task WHERE task_key = :task_key FOR UPDATE',
                ['task_key' => $execution['restore_task_key']]
            );
            if ($child === null || (string)$child['status'] !== 'succeeded') {
                if ($child !== null && in_array((string)$child['status'], ['queued', 'running'], true)) {
                    return ['action' => 'wait_restore', 'child_task_key' => (string)$child['task_key']];
                }
                throw new \RuntimeException('OPS_UPGRADE_RESTORE_FAILED');
            }
            $evidence = $this->one(
                'SELECT * FROM pa_ops_restore_evidence WHERE task_key = :task_key FOR UPDATE',
                ['task_key' => $child['task_key']]
            );
            if ($evidence === null
                || !hash_equals((string)$execution['backup_reference_key'], (string)$evidence['backup_reference_key'])
                || !hash_equals((string)$execution['source_commit'], (string)$evidence['source_commit'])
                || !hash_equals((string)$execution['source_tree'], (string)$evidence['source_tree'])
            ) {
                throw new \RuntimeException('OPS_UPGRADE_RESTORE_FAILED');
            }
            $this->succeedStep((string)$task['task_key'], 'restore_verification', (string)$evidence['evidence_sha256']);
            $this->startStep((string)$task['task_key'], 'maintenance');
            $update = $this->pdo->prepare(<<<'SQL'
UPDATE pa_ops_upgrade_execution
SET current_step = 'maintenance', restore_evidence_sha256 = :restore_sha,
    updated_at = UTC_TIMESTAMP(3)
WHERE task_key = :task_key AND current_step = 'restore_verification'
SQL);
            $update->execute([
                'restore_sha' => $evidence['evidence_sha256'],
                'task_key' => $task['task_key'],
            ]);
            if ($update->rowCount() !== 1) {
                throw new \RuntimeException('OPS_UPGRADE_STEP_CONFLICT');
            }
            return ['action' => 'begin_maintenance'];
        });
        if (($result['action'] ?? null) !== 'begin_maintenance') {
            return $result;
        }
        return $this->advanceMaintenance($task, $revision);
    }

    /** @param array<string,mixed> $task @return array<string,mixed> */
    private function advanceMaintenance(array $task, int $revision): array
    {
        return $this->transaction(function () use ($task, $revision): array {
            $this->lockedRunningTask((string)$task['task_key'], $revision);
            $execution = $this->execution((string)$task['task_key'], true);
            if ((string)$execution['current_step'] !== 'maintenance'
                || $execution['maintenance_key'] !== null
                || $execution['maintenance_revision'] !== null
            ) {
                throw new \RuntimeException('OPS_UPGRADE_STEP_INVALID');
            }
            $context = $this->context($task);
            $maintenanceKey = 'maintenance_' . bin2hex(random_bytes(16));
            $now = new DateTimeImmutable('now', new DateTimeZone('UTC'));
            $window = new MaintenanceWindow(
                $maintenanceKey,
                'active',
                'planned-upgrade',
                $now->modify('-1 minute')->format('Y-m-d\TH:i:s.v\Z'),
                $now->modify('+23 hours')->format('Y-m-d\TH:i:s.v\Z'),
                1,
            );
            $idempotencyDigest = hash('sha256', (string)$task['task_key'] . ':maintenance-open');
            $requestDigest = hash('sha256', $maintenanceKey . ':' . $window->startsAt . ':' . $window->endsAt);
            $created = (new PdoMaintenanceWindowStore($this->pdo, $this->audit))->schedule(
                $context,
                $window,
                0,
                $idempotencyDigest,
                $requestDigest,
                new OpsAuditEvent('platform.ops.maintenance.scheduled', 'maintenance.schedule', [
                    'maintenance_key' => $maintenanceKey,
                    'revision' => 1,
                    'idempotency_digest' => $idempotencyDigest,
                    'request_digest' => $requestDigest,
                ]),
            );
            $update = $this->pdo->prepare(<<<'SQL'
UPDATE pa_ops_upgrade_execution
SET maintenance_key = :maintenance_key, maintenance_revision = :maintenance_revision,
    updated_at = UTC_TIMESTAMP(3)
WHERE task_key = :task_key AND current_step = 'maintenance'
SQL);
            $update->execute([
                'maintenance_key' => $created->maintenanceKey,
                'maintenance_revision' => $created->revision,
                'task_key' => $task['task_key'],
            ]);
            if ($update->rowCount() !== 1) {
                throw new \RuntimeException('OPS_UPGRADE_STEP_CONFLICT');
            }

            $readiness = $this->runtimeStatus->upgradeReadiness($context);
            if (($readiness['state'] ?? null) !== 'ready'
                || !hash_equals((string)$execution['target_descriptor_sha256'], (string)($readiness['target']['descriptor_sha256'] ?? ''))
                || !hash_equals((string)$execution['backup_reference_key'], (string)($readiness['recovery_pointer']['backup_reference_key'] ?? ''))
            ) {
                throw new \RuntimeException('OPS_UPGRADE_MAINTENANCE_FAILED');
            }
            $this->succeedStep(
                (string)$task['task_key'],
                'maintenance',
                $this->digest(['maintenance_key' => $created->maintenanceKey, 'revision' => $created->revision])
            );
            $this->startStep((string)$task['task_key'], 'deployment');
            $move = $this->pdo->prepare(<<<'SQL'
UPDATE pa_ops_upgrade_execution
SET current_step = 'deployment', updated_at = UTC_TIMESTAMP(3)
WHERE task_key = :task_key AND current_step = 'maintenance'
SQL);
            $move->execute(['task_key' => $task['task_key']]);
            if ($move->rowCount() !== 1) {
                throw new \RuntimeException('OPS_UPGRADE_STEP_CONFLICT');
            }
            return [
                'action' => 'deploy',
                'target_release_key' => (string)$execution['target_release_key'],
            ];
        });
    }

    /** @param array<string,mixed> $payload */
    private function createExecution(string $taskKey, array $payload): void
    {
        $insert = $this->pdo->prepare(<<<'SQL'
INSERT IGNORE INTO pa_ops_upgrade_execution (
    task_key, source_commit, source_tree, source_release_key,
    source_application_manifest_sha256, target_release_key, target_commit,
    target_tree, target_descriptor_sha256, current_step
) VALUES (
    :task_key, :source_commit, :source_tree, :source_release_key,
    :source_application_manifest_sha256, :target_release_key, :target_commit,
    :target_tree, :target_descriptor_sha256, 'preflight'
)
SQL);
        $insert->execute([
            ...$payload,
            'task_key' => $taskKey,
            'source_release_key' => $payload['source_release_key'] === '' ? null : $payload['source_release_key'],
        ]);
        $inputBase = ['task_key' => $taskKey, 'source' => $payload['source_commit'], 'target' => $payload['target_commit'], 'descriptor' => $payload['target_descriptor_sha256']];
        $stepInsert = $this->pdo->prepare(<<<'SQL'
INSERT IGNORE INTO pa_ops_upgrade_step
  (task_key, step_key, step_order, status, input_sha256)
VALUES (:task_key, :step_key, :step_order, 'pending', :input_sha256)
SQL);
        foreach (self::STEPS as $order => $step) {
            $stepInsert->execute([
                'task_key' => $taskKey,
                'step_key' => $step,
                'step_order' => $order,
                'input_sha256' => $this->digest([...$inputBase, 'step' => $step]),
            ]);
        }
    }

    private function startStep(string $taskKey, string $step): void
    {
        $statement = $this->pdo->prepare(<<<'SQL'
UPDATE pa_ops_upgrade_step
SET status = 'running', started_at = UTC_TIMESTAMP(3)
WHERE task_key = :task_key AND step_key = :step_key AND status = 'pending'
SQL);
        $statement->execute(['task_key' => $taskKey, 'step_key' => $step]);
        if ($statement->rowCount() !== 1) {
            throw new \RuntimeException('OPS_UPGRADE_STEP_CONFLICT');
        }
    }

    private function succeedStep(string $taskKey, string $step, string $outputSha256): void
    {
        $statement = $this->pdo->prepare(<<<'SQL'
UPDATE pa_ops_upgrade_step
SET status = 'succeeded', output_sha256 = :output_sha256,
    last_error_code = NULL, completed_at = UTC_TIMESTAMP(3)
WHERE task_key = :task_key AND step_key = :step_key AND status = 'running'
SQL);
        $statement->execute([
            'output_sha256' => $outputSha256,
            'task_key' => $taskKey,
            'step_key' => $step,
        ]);
        if ($statement->rowCount() !== 1) {
            throw new \RuntimeException('OPS_UPGRADE_STEP_CONFLICT');
        }
    }

    private function failStep(string $taskKey, string $step, string $errorCode): void
    {
        $statement = $this->pdo->prepare(<<<'SQL'
UPDATE pa_ops_upgrade_step
SET status = 'failed', last_error_code = :error_code,
    completed_at = UTC_TIMESTAMP(3)
WHERE task_key = :task_key AND step_key = :step_key AND status = 'running'
SQL);
        $statement->execute([
            'error_code' => $errorCode,
            'task_key' => $taskKey,
            'step_key' => $step,
        ]);
        if ($statement->rowCount() !== 1) {
            throw new \RuntimeException('OPS_UPGRADE_STEP_CONFLICT');
        }
    }

    private function failStaleRunningTasks(): void
    {
        $statement = $this->pdo->query(<<<'SQL'
SELECT task.task_key, execution.current_step
FROM pa_ops_task task
JOIN pa_ops_upgrade_execution execution ON execution.task_key = task.task_key
WHERE task.task_type = 'ops.upgrade.execute' AND task.status = 'running'
  AND task.updated_at < UTC_TIMESTAMP(3) - INTERVAL 2 HOUR
FOR UPDATE
SQL);
        if ($statement === false) {
            throw new \RuntimeException('OPS_UPGRADE_TASK_UNAVAILABLE');
        }
        while (($row = $statement->fetch(PDO::FETCH_ASSOC)) !== false) {
            $this->failStep((string)$row['task_key'], (string)$row['current_step'], 'OPS_UPGRADE_WORKER_STALE');
            $update = $this->pdo->prepare(<<<'SQL'
UPDATE pa_ops_task
SET status = 'dead', revision = revision + 1,
    last_error_code = 'OPS_UPGRADE_WORKER_STALE',
    completed_at = UTC_TIMESTAMP(3), updated_at = UTC_TIMESTAMP(3)
WHERE task_key = :task_key AND status = 'running'
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
        $requestDigest = hash('sha256', $this->canonicalJson([
            'task_type' => $taskType,
            'payload' => $payload,
        ]));
        return new OpsTaskSubmission(
            $taskType,
            $handlerKey,
            $payload,
            $idempotencyDigest,
            $requestDigest,
            $concurrencyKey,
            $maximumAttempts,
            new OpsAuditEvent($eventType, $action, array_filter([
                'provider_key' => $payload['provider_key'],
                'target_key' => $payload['target_key'] ?? null,
                'idempotency_digest' => $idempotencyDigest,
                'request_digest' => $requestDigest,
            ], static fn(mixed $value): bool => $value !== null)),
        );
    }

    /** @param array<string,mixed> $task @return array<string,string> */
    private function payload(array $task): array
    {
        try {
            $payload = json_decode((string)$task['payload_json'], true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            throw new \RuntimeException('OPS_UPGRADE_PAYLOAD_INVALID');
        }
        $expected = [
            'source_application_manifest_sha256', 'source_commit', 'source_release_key',
            'source_tree', 'target_commit', 'target_descriptor_sha256',
            'target_release_key', 'target_tree',
        ];
        $keys = is_array($payload) ? array_keys($payload) : [];
        sort($keys, SORT_STRING);
        if ($keys !== $expected) {
            throw new \RuntimeException('OPS_UPGRADE_PAYLOAD_INVALID');
        }
        foreach ($payload as $value) {
            if (!is_string($value)) {
                throw new \RuntimeException('OPS_UPGRADE_PAYLOAD_INVALID');
            }
        }
        if (preg_match('/^[a-f0-9]{40}$/D', $payload['source_commit']) !== 1
            || preg_match('/^[a-f0-9]{40}$/D', $payload['source_tree']) !== 1
            || preg_match('/^[a-f0-9]{40}$/D', $payload['target_commit']) !== 1
            || preg_match('/^[a-f0-9]{40}$/D', $payload['target_tree']) !== 1
            || preg_match('/^[a-f0-9]{64}$/D', $payload['source_application_manifest_sha256']) !== 1
            || preg_match('/^[a-f0-9]{64}$/D', $payload['target_descriptor_sha256']) !== 1
            || preg_match('/^v[0-9]+\.[0-9]+\.[0-9]+$/D', $payload['target_release_key']) !== 1
            || ($payload['source_release_key'] !== ''
                && preg_match('/^v[0-9]+\.[0-9]+\.[0-9]+$/D', $payload['source_release_key']) !== 1)
        ) {
            throw new \RuntimeException('OPS_UPGRADE_PAYLOAD_INVALID');
        }
        return $payload;
    }

    /** @param array<string,mixed> $task */
    private function context(array $task): PlatformContext
    {
        return PlatformContext::fromValidatedSession(
            new ValidatedPlatformSession(
                1,
                'upgrade-worker-' . substr((string)$task['task_key'], 4, 16),
                (int)$task['account_id'],
                (int)$task['submitted_by_operator_id'],
                'platform-web',
                new DateTimeImmutable('now'),
            ),
            'upgrade-' . substr((string)$task['task_key'], 4),
        );
    }

    /** @return array<string,mixed> */
    private function runningTask(string $taskKey, int $revision): array
    {
        $task = $this->one(<<<'SQL'
SELECT task.*, operator.account_id
FROM pa_ops_task task
JOIN pa_platform_operator operator ON operator.id = task.submitted_by_operator_id
WHERE task.task_key = :task_key AND task.task_type = :task_type
  AND task.status = 'running' AND task.revision = :revision
SQL, ['task_key' => $taskKey, 'task_type' => PlatformUpgradeExecutionService::TASK_TYPE, 'revision' => $revision]);
        if ($task === null) {
            throw new \RuntimeException('OPS_UPGRADE_EXECUTION_FENCED');
        }
        return $task;
    }

    /** @return array<string,mixed> */
    private function lockedRunningTask(string $taskKey, int $revision): array
    {
        $task = $this->one(<<<'SQL'
SELECT task.*, operator.account_id
FROM pa_ops_task task
JOIN pa_platform_operator operator ON operator.id = task.submitted_by_operator_id
WHERE task.task_key = :task_key AND task.task_type = :task_type
  AND task.status = 'running' AND task.revision = :revision
FOR UPDATE
SQL, ['task_key' => $taskKey, 'task_type' => PlatformUpgradeExecutionService::TASK_TYPE, 'revision' => $revision]);
        if ($task === null) {
            throw new \RuntimeException('OPS_UPGRADE_EXECUTION_FENCED');
        }
        return $task;
    }

    /** @return array<string,mixed> */
    private function execution(string $taskKey, bool $forUpdate = false): array
    {
        $row = $this->one(
            'SELECT * FROM pa_ops_upgrade_execution WHERE task_key = :task_key' . ($forUpdate ? ' FOR UPDATE' : ''),
            ['task_key' => $taskKey]
        );
        if ($row === null) {
            throw new \RuntimeException('OPS_UPGRADE_EXECUTION_UNAVAILABLE');
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
        $this->audit->recordPlatform(
            $eventType,
            $action,
            'upgrade-' . substr((string)$task['task_key'], 4),
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

    private function transaction(callable $operation): mixed
    {
        $ownsTransaction = !$this->pdo->inTransaction();
        if ($ownsTransaction) {
            $this->pdo->beginTransaction();
        }
        try {
            $result = $operation();
            if ($ownsTransaction) {
                $this->pdo->commit();
            }
            return $result;
        } catch (Throwable $exception) {
            if ($ownsTransaction && $this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $exception;
        }
    }

    /** @param array<string,mixed> $value */
    private function digest(array $value): string
    {
        return hash('sha256', $this->canonicalJson($value));
    }

    /** @param array<string,mixed> $value */
    private function canonicalJson(array $value): string
    {
        ksort($value, SORT_STRING);
        return json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
    }

    private function instant(string $value): string
    {
        $normalized = str_replace(' ', 'T', trim($value));
        if (!str_contains($normalized, '.')) {
            $normalized .= '.000';
        }
        return $normalized . 'Z';
    }
}
