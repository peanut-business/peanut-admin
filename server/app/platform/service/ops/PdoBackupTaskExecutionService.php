<?php
declare(strict_types=1);

namespace app\platform\service\ops;

use app\common\service\audit\AuditContractHost;
use PDO;
use PeanutAdmin\OpsConsole\Package;
use RuntimeException;
use Throwable;

/** Trusted deployment-worker boundary; no HTTP controller calls this service. */
final readonly class PdoBackupTaskExecutionService
{
    private const FAILURE_CODES = [
        'OPS_BACKUP_CAPACITY_INSUFFICIENT',
        'OPS_BACKUP_QUIESCENCE_FAILED',
        'OPS_BACKUP_DATABASE_FAILED',
        'OPS_BACKUP_FILES_FAILED',
        'OPS_BACKUP_MANIFEST_INVALID',
        'OPS_BACKUP_INTEGRITY_FAILED',
        'OPS_BACKUP_RUNTIME_FAILED',
    ];

    public function __construct(private PDO $pdo)
    {
    }

    /** @return array{task_key:string,backup_reference_key:string,provider_key:string,execution_revision:int}|null */
    public function claim(): ?array
    {
        return $this->transaction(function (): ?array {
            $this->failStaleRunningTasks();
            $statement = $this->pdo->prepare(<<<'SQL'
SELECT id, task_key, payload_json, attempt_count, max_attempts, revision
FROM pa_ops_task
WHERE task_type = :task_type
  AND handler_key = :handler_key
  AND status = 'queued'
  AND available_at <= UTC_TIMESTAMP(3)
ORDER BY id ASC
LIMIT 1
FOR UPDATE SKIP LOCKED
SQL);
            $statement->execute([
                'task_type' => Package::BACKUP_TASK_TYPE,
                'handler_key' => PairedBackupProvider::BACKUP_HANDLER_KEY,
            ]);
            $row = $statement->fetch(PDO::FETCH_ASSOC);
            if (!is_array($row)) {
                return null;
            }

            $payload = json_decode((string)$row['payload_json'], true, 8, JSON_THROW_ON_ERROR);
            if (!is_array($payload)
                || array_keys($payload) !== ['provider_key']
                || ($payload['provider_key'] ?? null) !== PairedBackupProvider::PROVIDER_KEY
                || (int)$row['attempt_count'] >= (int)$row['max_attempts']
            ) {
                throw new RuntimeException('OPS_BACKUP_TASK_INVALID');
            }

            $update = $this->pdo->prepare(<<<'SQL'
UPDATE pa_ops_task
SET status = 'running', attempt_count = attempt_count + 1,
    revision = revision + 1, updated_at = UTC_TIMESTAMP(3)
WHERE id = :id AND status = 'queued' AND revision = :revision
SQL);
            $update->execute(['id' => $row['id'], 'revision' => $row['revision']]);
            if ($update->rowCount() !== 1) {
                throw new RuntimeException('OPS_BACKUP_TASK_CLAIM_CONFLICT');
            }

            $taskKey = (string)$row['task_key'];
            return [
                'task_key' => $taskKey,
                'backup_reference_key' => 'backup_' . substr($taskKey, 4),
                'provider_key' => PairedBackupProvider::PROVIDER_KEY,
                'execution_revision' => (int)$row['revision'] + 1,
            ];
        });
    }

    /** @return array{task_key:string,status:string,execution_revision:int} */
    public function heartbeat(string $taskKey, int $executionRevision): array
    {
        $this->taskSuffix($taskKey);
        $this->assertExecutionRevision($executionRevision);

        return $this->transaction(function () use ($taskKey, $executionRevision): array {
            $task = $this->taskForUpdate($taskKey);
            if ((string)$task['status'] !== 'running'
                || (int)$task['revision'] !== $executionRevision
            ) {
                throw new RuntimeException('OPS_BACKUP_EXECUTION_FENCED');
            }
            $statement = $this->pdo->prepare(<<<'SQL'
UPDATE pa_ops_task
SET updated_at = UTC_TIMESTAMP(3)
WHERE task_key = :task_key AND status = 'running' AND revision = :revision
SQL);
            $statement->execute(['task_key' => $taskKey, 'revision' => $executionRevision]);
            return [
                'task_key' => $taskKey,
                'status' => 'running',
                'execution_revision' => $executionRevision,
            ];
        });
    }

    private function failStaleRunningTasks(): void
    {
        $statement = $this->pdo->prepare(<<<'SQL'
SELECT task.*, operator.account_id
FROM pa_ops_task AS task
INNER JOIN pa_platform_operator AS operator ON operator.id = task.submitted_by_operator_id
WHERE task.task_type = :task_type
  AND task.handler_key = :handler_key
  AND task.status = 'running'
  AND task.updated_at < TIMESTAMPADD(HOUR, -2, UTC_TIMESTAMP(3))
ORDER BY task.id ASC
FOR UPDATE
SQL);
        $statement->execute([
            'task_type' => Package::BACKUP_TASK_TYPE,
            'handler_key' => PairedBackupProvider::BACKUP_HANDLER_KEY,
        ]);
        foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $task) {
            $update = $this->pdo->prepare(<<<'SQL'
UPDATE pa_ops_task
SET status = 'dead', revision = revision + 1,
    last_error_code = 'OPS_BACKUP_RUNTIME_FAILED',
    updated_at = UTC_TIMESTAMP(3), completed_at = UTC_TIMESTAMP(3)
WHERE id = :id AND status = 'running'
SQL);
            $update->execute(['id' => $task['id']]);
            if ($update->rowCount() === 1) {
                $this->audit($task, 'platform.ops.backup.failed', 'backup.fail', [
                    'task_key' => (string)$task['task_key'],
                    'provider_key' => PairedBackupProvider::PROVIDER_KEY,
                ]);
            }
        }
    }

    /** @return array{task_key:string,backup_reference_key:string,manifest_sha256:string,status:string} */
    public function succeed(string $taskKey, int $executionRevision, string $manifestJson): array
    {
        $this->assertExecutionRevision($executionRevision);
        $manifest = PairedBackupManifest::fromJson($manifestJson);
        $canonical = $manifest->canonicalJson();
        $manifestArray = $manifest->toArray();
        $expectedReference = 'backup_' . $this->taskSuffix($taskKey);
        if (!hash_equals($expectedReference, $manifest->backupReferenceKey())) {
            throw new RuntimeException('OPS_BACKUP_REFERENCE_MISMATCH');
        }

        return $this->transaction(function () use ($taskKey, $executionRevision, $manifest, $manifestArray, $canonical): array {
            $task = $this->taskForUpdate($taskKey);
            $existing = $this->evidence($taskKey);
            $sha256 = hash('sha256', $canonical);
            if ($existing !== null) {
                if (!hash_equals((string)$existing['manifest_sha256'], $sha256)) {
                    throw new RuntimeException('OPS_BACKUP_EVIDENCE_CONFLICT');
                }
                return [
                    'task_key' => $taskKey,
                    'backup_reference_key' => $manifest->backupReferenceKey(),
                    'manifest_sha256' => $sha256,
                    'status' => 'succeeded',
                ];
            }
            if ((string)$task['status'] !== 'running'
                || (int)$task['revision'] !== $executionRevision
            ) {
                throw new RuntimeException('OPS_BACKUP_EXECUTION_FENCED');
            }

            $source = $manifestArray['source'];
            $window = $manifestArray['consistency_window'];
            $insert = $this->pdo->prepare(<<<'SQL'
INSERT INTO pa_ops_backup_evidence (
    backup_reference_key, task_key, provider_key, manifest_sha256,
    source_commit, source_tree, source_release_key,
    consistency_started_at, consistency_completed_at, verified_at, manifest_json
) VALUES (
    :backup_reference_key, :task_key, :provider_key, :manifest_sha256,
    :source_commit, :source_tree, :source_release_key,
    :started_at, :completed_at, UTC_TIMESTAMP(3), :manifest_json
)
SQL);
            $insert->execute([
                'backup_reference_key' => $manifest->backupReferenceKey(),
                'task_key' => $taskKey,
                'provider_key' => PairedBackupProvider::PROVIDER_KEY,
                'manifest_sha256' => $sha256,
                'source_commit' => $source['commit'],
                'source_tree' => $source['tree'],
                'source_release_key' => $source['release_key'],
                'started_at' => $this->databaseInstant($window['started_at']),
                'completed_at' => $this->databaseInstant($window['completed_at']),
                'manifest_json' => $canonical,
            ]);

            $update = $this->pdo->prepare(<<<'SQL'
UPDATE pa_ops_task
SET status = 'succeeded', revision = revision + 1, last_error_code = NULL,
    updated_at = UTC_TIMESTAMP(3), completed_at = UTC_TIMESTAMP(3)
WHERE task_key = :task_key AND status = 'running' AND revision = :revision
SQL);
            $update->execute(['task_key' => $taskKey, 'revision' => $executionRevision]);
            if ($update->rowCount() !== 1) {
                throw new RuntimeException('OPS_BACKUP_TASK_STATE_CONFLICT');
            }
            $this->audit($task, 'platform.ops.backup.succeeded', 'backup.succeed', [
                'task_key' => $taskKey,
                'provider_key' => PairedBackupProvider::PROVIDER_KEY,
            ]);

            return [
                'task_key' => $taskKey,
                'backup_reference_key' => $manifest->backupReferenceKey(),
                'manifest_sha256' => $sha256,
                'status' => 'succeeded',
            ];
        });
    }

    /** @return array{task_key:string,status:string,last_error_code:string} */
    public function fail(string $taskKey, int $executionRevision, string $errorCode): array
    {
        if (!in_array($errorCode, self::FAILURE_CODES, true)) {
            throw new RuntimeException('OPS_BACKUP_FAILURE_CODE_INVALID');
        }
        $this->taskSuffix($taskKey);
        $this->assertExecutionRevision($executionRevision);

        return $this->transaction(function () use ($taskKey, $executionRevision, $errorCode): array {
            $task = $this->taskForUpdate($taskKey);
            if ((string)$task['status'] === 'dead' && hash_equals((string)$task['last_error_code'], $errorCode)) {
                return ['task_key' => $taskKey, 'status' => 'dead', 'last_error_code' => $errorCode];
            }
            if ((string)$task['status'] !== 'running'
                || (int)$task['revision'] !== $executionRevision
            ) {
                throw new RuntimeException('OPS_BACKUP_EXECUTION_FENCED');
            }
            $statement = $this->pdo->prepare(<<<'SQL'
UPDATE pa_ops_task
SET status = 'dead', revision = revision + 1, last_error_code = :error_code,
    updated_at = UTC_TIMESTAMP(3), completed_at = UTC_TIMESTAMP(3)
WHERE task_key = :task_key AND status = 'running' AND revision = :revision
SQL);
            $statement->execute([
                'task_key' => $taskKey,
                'revision' => $executionRevision,
                'error_code' => $errorCode,
            ]);
            if ($statement->rowCount() !== 1) {
                throw new RuntimeException('OPS_BACKUP_TASK_STATE_CONFLICT');
            }
            $this->audit($task, 'platform.ops.backup.failed', 'backup.fail', [
                'task_key' => $taskKey,
                'provider_key' => PairedBackupProvider::PROVIDER_KEY,
            ]);
            return ['task_key' => $taskKey, 'status' => 'dead', 'last_error_code' => $errorCode];
        });
    }

    /** @return array<string,mixed> */
    private function taskForUpdate(string $taskKey): array
    {
        $statement = $this->pdo->prepare(<<<'SQL'
SELECT task.*, operator.account_id
FROM pa_ops_task AS task
INNER JOIN pa_platform_operator AS operator ON operator.id = task.submitted_by_operator_id
WHERE task.task_key = :task_key
  AND task.task_type = :task_type
  AND task.handler_key = :handler_key
FOR UPDATE
SQL);
        $statement->execute([
            'task_key' => $taskKey,
            'task_type' => Package::BACKUP_TASK_TYPE,
            'handler_key' => PairedBackupProvider::BACKUP_HANDLER_KEY,
        ]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        if (!is_array($row)) {
            throw new RuntimeException('OPS_BACKUP_TASK_NOT_FOUND');
        }
        return $row;
    }

    /** @return array<string,mixed>|null */
    private function evidence(string $taskKey): ?array
    {
        $statement = $this->pdo->prepare(
            'SELECT manifest_sha256 FROM pa_ops_backup_evidence WHERE task_key = :task_key FOR UPDATE'
        );
        $statement->execute(['task_key' => $taskKey]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        return is_array($row) ? $row : null;
    }

    /** @param array<string,mixed> $task @param array<string,string> $metadata */
    private function audit(array $task, string $eventType, string $action, array $metadata): void
    {
        AuditContractHost::fromPdo($this->pdo)->appendPlatform(
            $eventType,
            $action,
            'ops-worker-' . bin2hex(random_bytes(16)),
            (int)$task['submitted_by_operator_id'],
            (int)$task['account_id'],
            $metadata
        );
    }

    private function taskSuffix(string $taskKey): string
    {
        if (preg_match('/^job_([a-f0-9]{32})$/D', $taskKey, $matches) !== 1) {
            throw new RuntimeException('OPS_BACKUP_TASK_KEY_INVALID');
        }
        return $matches[1];
    }

    private function databaseInstant(string $instant): string
    {
        return str_replace(['T', 'Z'], [' ', ''], $instant);
    }

    private function assertExecutionRevision(int $executionRevision): void
    {
        if ($executionRevision < 2) {
            throw new RuntimeException('OPS_BACKUP_EXECUTION_REVISION_INVALID');
        }
    }

    /** @template T @param callable():T $operation @return T */
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
}
