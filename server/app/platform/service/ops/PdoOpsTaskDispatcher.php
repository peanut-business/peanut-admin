<?php
declare(strict_types=1);

namespace app\platform\service\ops;

use app\common\service\audit\AuditContractHost;
use PDO;
use PeanutAdmin\Kernel\Audit\AuditOutcome;
use PeanutAdmin\Kernel\Context\PlatformContext;
use PeanutAdmin\OpsConsole\Application\OpsConsoleException;
use PeanutAdmin\OpsConsole\Task\OpsTask;
use PeanutAdmin\OpsConsole\Task\OpsTaskDispatcher;
use PeanutAdmin\OpsConsole\Task\OpsTaskSubmission;
use Throwable;

/** Application persistence adapter for Core operations tasks. */
final readonly class PdoOpsTaskDispatcher implements OpsTaskDispatcher
{
    public function __construct(
        private PDO $pdo,
        private AuditContractHost $audit,
    ) {
    }

    public function dispatch(PlatformContext $context, OpsTaskSubmission $submission): OpsTask
    {
        return $this->map($this->dispatchRow(
            $context,
            $submission->taskType,
            $submission->handlerKey,
            $submission->payload,
            $submission->idempotencyDigest,
            $submission->requestDigest,
            $submission->concurrencyKey,
            $submission->maximumAttempts,
            $submission->audit->eventType,
            $submission->audit->action,
            $submission->audit->metadata,
        ));
    }

    /**
     * Application-owned PC42 extension over the same canonical task ledger.
     *
     * @param array<string,string> $payload
     * @return array<string,mixed>
     */
    public function dispatchUpgrade(
        PlatformContext $context,
        array $payload,
        string $idempotencyKey,
    ): array {
        if (strlen($idempotencyKey) < 8 || strlen($idempotencyKey) > 200
            || preg_match('/^[\x21-\x7e]+$/D', $idempotencyKey) !== 1
        ) {
            throw OpsConsoleException::invalid();
        }
        $idempotencyDigest = hash('sha256', $idempotencyKey);
        $requestDigest = hash('sha256', json_encode(
            ['task_type' => PlatformUpgradeExecutionService::TASK_TYPE, 'payload' => $payload],
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES,
        ));

        return $this->dispatchRow(
            $context,
            PlatformUpgradeExecutionService::TASK_TYPE,
            PlatformUpgradeExecutionService::HANDLER_KEY,
            $payload,
            $idempotencyDigest,
            $requestDigest,
            PlatformUpgradeExecutionService::CONCURRENCY_KEY,
            1,
            'platform.ops.upgrade.submitted',
            'upgrade.submit',
            [
                'target_release_key' => $payload['target_release_key'],
                'target_commit' => $payload['target_commit'],
                'target_descriptor_sha256' => $payload['target_descriptor_sha256'],
                'idempotency_digest' => $idempotencyDigest,
                'request_digest' => $requestDigest,
            ],
        );
    }

    /**
     * @param array<string,string> $payload
     * @return array<string,mixed>
     */
    public function dispatchModuleOperation(
        PlatformContext $context,
        array $payload,
        string $idempotencyKey,
    ): array {
        if (strlen($idempotencyKey) < 8 || strlen($idempotencyKey) > 200
            || preg_match('/^[\x21-\x7e]+$/D', $idempotencyKey) !== 1
        ) {
            throw OpsConsoleException::invalid();
        }
        $idempotencyDigest = hash('sha256', $idempotencyKey);
        $requestDigest = hash('sha256', json_encode(
            ['task_type' => PlatformModuleOperationExecutionService::TASK_TYPE, 'payload' => $payload],
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES,
        ));
        return $this->dispatchRow(
            $context,
            PlatformModuleOperationExecutionService::TASK_TYPE,
            PlatformModuleOperationExecutionService::HANDLER_KEY,
            $payload,
            $idempotencyDigest,
            $requestDigest,
            PlatformModuleOperationExecutionService::CONCURRENCY_KEY,
            1,
            'platform.ops.module.submitted',
            'module.submit',
            [
                'request_key' => $payload['request_key'],
                'environment' => $payload['environment'],
                'target_resource_id' => $payload['target_resource_id'],
                'package_key' => $payload['package_key'],
                'operation' => $payload['operation'],
                'idempotency_digest' => $idempotencyDigest,
                'request_digest' => $requestDigest,
            ],
        );
    }

    /**
     * @param array<string,string> $payload
     * @param array<string,bool|int|string|null> $auditMetadata
     * @return array<string,mixed>
     */
    private function dispatchRow(
        PlatformContext $context,
        string $taskType,
        string $handlerKey,
        array $payload,
        string $idempotencyDigest,
        string $requestDigest,
        string $concurrencyKey,
        int $maximumAttempts,
        string $eventType,
        string $action,
        array $auditMetadata,
    ): array {
        $ownsTransaction = !$this->pdo->inTransaction();
        if ($ownsTransaction) {
            $this->pdo->beginTransaction();
        }

        try {
            $existing = $this->one(
                'SELECT * FROM pa_ops_task WHERE submitted_by_operator_id = :operator_id AND idempotency_digest = :digest FOR UPDATE',
                ['operator_id' => $context->operatorId, 'digest' => $idempotencyDigest]
            );
            if ($existing !== null) {
                if (!hash_equals((string)$existing['request_digest'], $requestDigest)) {
                    throw OpsConsoleException::idempotencyConflict();
                }
                if ($ownsTransaction) {
                    $this->pdo->commit();
                }
                return $existing;
            }

            $active = $this->one(
                "SELECT id FROM pa_ops_task WHERE concurrency_key = :concurrency_key AND status IN ('queued', 'running') LIMIT 1 FOR UPDATE",
                ['concurrency_key' => $concurrencyKey]
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
                'task_type' => $taskType,
                'handler_key' => $handlerKey,
                'payload_json' => json_encode(
                    $payload,
                    JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES
                ),
                'max_attempts' => $maximumAttempts,
                'idempotency_digest' => $idempotencyDigest,
                'request_digest' => $requestDigest,
                'concurrency_key' => $concurrencyKey,
                'operator_id' => $context->operatorId,
            ]);

            $this->audit->recordPlatform(
                $eventType,
                $action,
                $context->requestId,
                $context->operatorId,
                $context->accountId,
                [...$auditMetadata, 'task_key' => $taskKey],
                AuditOutcome::Success,
                null,
            );

            $row = $this->one('SELECT * FROM pa_ops_task WHERE task_key = :task_key', ['task_key' => $taskKey]);
            if ($row === null) {
                throw OpsConsoleException::taskUnavailable();
            }
            if ($ownsTransaction) {
                $this->pdo->commit();
            }
            return $row;
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
