<?php
declare(strict_types=1);

namespace app\platform\service\ops;

use PDO;
use PeanutAdmin\Kernel\Context\PlatformContext;
use PeanutAdmin\OpsConsole\Application\OpsConsoleException;
use PeanutAdmin\OpsConsole\Package;
use Throwable;
use Closure;

/** Platform projection for opaque deployment-staged Module requests. */
final readonly class PlatformModuleOperationExecutionService
{
    public const TASK_TYPE = 'ops.module.execute';
    public const HANDLER_KEY = 'peanut.module.delivery';
    public const CONCURRENCY_KEY = 'ops.module.execute.production';
    public const PERMISSION = 'platform.ops.module.manage';

    public function __construct(
        private PDO $pdo,
        private PdoOpsTaskDispatcher $tasks,
        private DeploymentModuleRequestService $requests,
        private ApplicationRuntimeStatusProvider|Closure $runtimeStatus,
    ) {
    }

    /** @return array<string,mixed> */
    public function submit(PlatformContext $context, string $requestKey, string $idempotencyKey): array
    {
        $permissions = new PlatformOpsPermissionChecker($this->pdo);
        if (!$permissions->allows($context, self::PERMISSION)
            || !$permissions->allows($context, Package::READ_PERMISSION)
        ) {
            throw OpsConsoleException::denied();
        }
        $ownsTransaction = !$this->pdo->inTransaction();
        if ($ownsTransaction) {
            $this->pdo->beginTransaction();
        }
        try {
            $request = $this->requestStore()->assertPrepared($requestKey);
            $runtime = $this->runtime($context);
            if ($runtime['health'] === 'unhealthy' || !$runtime['repository_clean']
                || preg_match('/^[a-f0-9]{40}$/D', $runtime['commit']) !== 1
                || preg_match('/^[a-f0-9]{40}$/D', $runtime['tree']) !== 1
            ) {
                throw OpsConsoleException::providerUnavailable();
            }
            $payload = [
                'request_key' => (string)$request['request_key'],
                'request_sha256' => (string)$request['request_sha256'],
                'environment' => (string)$request['environment'],
                'target_resource_id' => (string)$request['target_resource_id'],
                'delivery_resource_id' => (string)$request['delivery_resource_id'],
                'operation' => (string)$request['operation'],
                'package_key' => (string)$request['package_key'],
                'archive_sha256' => $request['archive_sha256'] === null ? '' : (string)$request['archive_sha256'],
                'signature_key_id' => $request['signature_key_id'] === null ? '' : (string)$request['signature_key_id'],
                'confirm_plan_json' => $request['confirm_plan_json'] === null ? '' : (string)$request['confirm_plan_json'],
                'confirm_plan_sha256' => $request['confirm_plan_sha256'] === null ? '' : (string)$request['confirm_plan_sha256'],
                'source_commit' => $runtime['commit'],
                'source_tree' => $runtime['tree'],
            ];
            $row = $this->tasks
                ->dispatchModuleOperation($context, $payload, $idempotencyKey);
            $claim = $this->pdo->prepare(<<<'SQL'
UPDATE pa_ops_module_request
SET state='claimed', claimed_at=UTC_TIMESTAMP(3)
WHERE request_key=:request_key AND state='prepared'
SQL);
            $claim->execute(['request_key' => $requestKey]);
            if ($ownsTransaction) {
                $this->pdo->commit();
            }
            return $this->taskProjection($row);
        } catch (Throwable $exception) {
            if ($ownsTransaction && $this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $exception;
        }
    }

    /** @return array<string,mixed>|null */
    public function taskIfModuleOperation(PlatformContext $context, string $taskKey): ?array
    {
        $this->assertRead($context);
        if (preg_match('/^job_[a-f0-9]{32}$/D', $taskKey) !== 1) {
            return null;
        }
        $statement = $this->pdo->prepare(
            'SELECT * FROM pa_ops_task WHERE task_key=:task_key AND task_type=:task_type'
        );
        $statement->execute(['task_key' => $taskKey, 'task_type' => self::TASK_TYPE]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        return is_array($row) ? $this->taskProjection($row) : null;
    }

    /** @return array{tasks:list<array<string,mixed>>} */
    public function snapshot(PlatformContext $context): array
    {
        $this->assertRead($context);
        $statement = $this->pdo->query(
            "SELECT * FROM pa_ops_task WHERE task_type='ops.module.execute' ORDER BY id DESC LIMIT 10"
        );
        $tasks = [];
        while ($statement !== false && ($row = $statement->fetch(PDO::FETCH_ASSOC)) !== false) {
            $tasks[] = $this->taskProjection($row);
        }
        return ['tasks' => $tasks];
    }

    /** @param array<string,mixed> $task @return array<string,mixed> */
    private function taskProjection(array $task): array
    {
        $payload = json_decode((string)$task['payload_json'], true, 64, JSON_THROW_ON_ERROR);
        if (!is_array($payload)) {
            throw OpsConsoleException::taskUnavailable();
        }
        $statement = $this->pdo->prepare('SELECT * FROM pa_ops_module_execution WHERE task_key=:task_key');
        $statement->execute(['task_key' => $task['task_key']]);
        $execution = $statement->fetch(PDO::FETCH_ASSOC);
        $pointer = null;
        if (is_array($execution) && is_string($execution['recovery_pointer_json'] ?? null)) {
            $decoded = json_decode($execution['recovery_pointer_json'], true, 64, JSON_THROW_ON_ERROR);
            $pointer = is_array($decoded) ? $decoded : null;
        }
        return [
            'task_key' => (string)$task['task_key'],
            'task_type' => self::TASK_TYPE,
            'status' => (string)$task['status'],
            'attempt_count' => (int)$task['attempt_count'],
            'revision' => (int)$task['revision'],
            'last_error_code' => $task['last_error_code'] === null ? null : (string)$task['last_error_code'],
            'request_key' => (string)$payload['request_key'],
            'environment' => (string)$payload['environment'],
            'target_resource_id' => (string)$payload['target_resource_id'],
            'operation' => (string)$payload['operation'],
            'package_key' => (string)$payload['package_key'],
            'current_step' => is_array($execution) ? (string)$execution['current_step'] : 'preflight',
            'backup_reference_key' => is_array($execution) ? $execution['backup_reference_key'] : null,
            'restore_evidence_sha256' => is_array($execution) ? $execution['restore_evidence_sha256'] : null,
            'maintenance_key' => is_array($execution) ? $execution['maintenance_key'] : null,
            'recovery_pointer' => $pointer,
            'recovery_pointer_sha256' => is_array($execution) ? $execution['recovery_pointer_sha256'] : null,
            'created_at' => $this->instant((string)$task['created_at']),
            'updated_at' => $this->instant((string)$task['updated_at']),
            'completed_at' => $task['completed_at'] === null ? null : $this->instant((string)$task['completed_at']),
        ];
    }

    private function requestStore(): DeploymentModuleRequestService
    {
        return $this->requests;
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

    private function assertRead(PlatformContext $context): void
    {
        if (!(new PlatformOpsPermissionChecker($this->pdo))->allows($context, Package::READ_PERMISSION)) {
            throw OpsConsoleException::denied();
        }
    }

    private function instant(string $value): string
    {
        $normalized = str_replace(' ', 'T', trim($value));
        return $normalized . (str_contains($normalized, '.') ? 'Z' : '.000Z');
    }
}
