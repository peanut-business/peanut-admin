<?php
declare(strict_types=1);

namespace app\platform\service\ops;

use app\common\service\audit\AuditContractHost;
use PDO;
use PeanutAdmin\Kernel\Context\PlatformContext;
use PeanutAdmin\OpsConsole\Application\OpsConsoleException;
use PeanutAdmin\OpsConsole\Task\OpsTask;
use PeanutAdmin\OpsConsole\Task\OpsTaskDispatcher;
use PeanutAdmin\OpsConsole\Task\OpsTaskSubmission;
use Throwable;

/** Application persistence adapter for Core operations tasks. */
final readonly class PdoOpsTaskDispatcher implements OpsTaskDispatcher
{
    public function __construct(private PDO $pdo)
    {
    }

    public function dispatch(PlatformContext $context, OpsTaskSubmission $submission): OpsTask
    {
        $ownsTransaction = !$this->pdo->inTransaction();
        if ($ownsTransaction) {
            $this->pdo->beginTransaction();
        }

        try {
            $existing = $this->one(
                'SELECT * FROM pa_ops_task WHERE submitted_by_operator_id = :operator_id AND idempotency_digest = :digest FOR UPDATE',
                ['operator_id' => $context->operatorId, 'digest' => $submission->idempotencyDigest]
            );
            if ($existing !== null) {
                if (!hash_equals((string)$existing['request_digest'], $submission->requestDigest)) {
                    throw OpsConsoleException::idempotencyConflict();
                }
                if ($ownsTransaction) {
                    $this->pdo->commit();
                }
                return $this->map($existing);
            }

            $active = $this->one(
                "SELECT id FROM pa_ops_task WHERE concurrency_key = :concurrency_key AND status IN ('queued', 'running') LIMIT 1 FOR UPDATE",
                ['concurrency_key' => $submission->concurrencyKey]
            );
            if ($active !== null) {
                throw OpsConsoleException::operationInProgress();
            }

            $taskKey = 'job_' . bin2hex(random_bytes(16));
            $statement = $this->pdo->prepare(<<<'SQL'
INSERT INTO pa_ops_task (
    task_key, task_type, handler_key, payload_json, status, attempt_count,
    max_attempts, revision, last_error_code, idempotency_digest,
    request_digest, concurrency_key, submitted_by_operator_id,
    available_at, created_at, updated_at, completed_at
) VALUES (
    :task_key, :task_type, :handler_key, :payload_json, 'queued', 0,
    :max_attempts, 1, NULL, :idempotency_digest,
    :request_digest, :concurrency_key, :operator_id,
    UTC_TIMESTAMP(3), UTC_TIMESTAMP(3), UTC_TIMESTAMP(3), NULL
)
SQL);
            $statement->execute([
                'task_key' => $taskKey,
                'task_type' => $submission->taskType,
                'handler_key' => $submission->handlerKey,
                'payload_json' => json_encode(
                    $submission->payload,
                    JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES
                ),
                'max_attempts' => $submission->maximumAttempts,
                'idempotency_digest' => $submission->idempotencyDigest,
                'request_digest' => $submission->requestDigest,
                'concurrency_key' => $submission->concurrencyKey,
                'operator_id' => $context->operatorId,
            ]);

            AuditContractHost::fromPdo($this->pdo)->appendPlatform(
                $submission->audit->eventType,
                $submission->audit->action,
                $context->requestId,
                $context->operatorId,
                $context->accountId,
                [...$submission->audit->metadata, 'task_key' => $taskKey]
            );

            $row = $this->one('SELECT * FROM pa_ops_task WHERE task_key = :task_key', ['task_key' => $taskKey]);
            if ($row === null) {
                throw OpsConsoleException::taskUnavailable();
            }
            if ($ownsTransaction) {
                $this->pdo->commit();
            }
            return $this->map($row);
        } catch (Throwable $exception) {
            if ($ownsTransaction && $this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $exception;
        }
    }

    public function find(PlatformContext $context, string $taskKey): OpsTask
    {
        $row = $this->one(
            'SELECT * FROM pa_ops_task WHERE task_key = :task_key',
            ['task_key' => $taskKey]
        );
        if ($row === null) {
            throw OpsConsoleException::taskNotFound();
        }
        return $this->map($row);
    }

    /** @param array<string, mixed> $parameters @return array<string, mixed>|null */
    private function one(string $sql, array $parameters): ?array
    {
        $statement = $this->pdo->prepare($sql);
        $statement->execute($parameters);
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        return is_array($row) ? $row : null;
    }

    /** @param array<string, mixed> $row */
    private function map(array $row): OpsTask
    {
        return new OpsTask(
            (string)$row['task_key'],
            (string)$row['task_type'],
            (string)$row['status'],
            (int)$row['attempt_count'],
            (int)$row['max_attempts'],
            (int)$row['revision'],
            $row['last_error_code'] === null ? null : (string)$row['last_error_code'],
            $this->instant((string)$row['available_at']),
            $this->instant((string)$row['created_at']),
            $this->instant((string)$row['updated_at']),
            $row['completed_at'] === null ? null : $this->instant((string)$row['completed_at'])
        );
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
