<?php
declare(strict_types=1);

namespace app\Modules\Official\Task\Application;

use app\common\enum\CrontabEnum;
use app\common\execution\CurrentExecutionContext;
use app\common\execution\ExecutionContext;
use app\common\execution\ExecutionContextStore;
use app\common\service\CrontabCommandService;
use app\common\service\crontab\CrontabTenantRepository;
use app\common\service\module\ModuleExecutionBoundary;
use app\Modules\Official\Task\Contracts\TaskWorkerDefinition;
use DateTimeImmutable;
use DateTimeZone;
use PDO;
use PeanutAdmin\Kernel\Async\VerifiedJobEnvelope;
use PeanutAdmin\Kernel\Auth\TenantContext;
use PeanutAdmin\Kernel\Auth\ValidatedTenantSession;
use PeanutAdmin\Kernel\Context\AuthorizationDecision;
use PeanutAdmin\Kernel\Context\AuthorizedOperationContext;
use PeanutAdmin\Kernel\Context\TenantSystemContext;
use PeanutAdmin\Kernel\Tenancy\ScheduledTenantContext;
use PeanutAdmin\Kernel\Tenancy\TenantScope;
use PeanutAdmin\TaskJob\Execution\JobExecution;
use PeanutAdmin\TaskJob\Execution\RetryableTaskException;
use PeanutAdmin\TaskJob\Execution\TaskHandler;
use PeanutAdmin\TaskJob\Submission\TaskSubmission;
use PeanutAdmin\TaskJob\Submission\TaskSubmissionProvider;
use think\facade\Console;

/** The built-in Task definition for one claimed Crontab schedule window. */
final class CrontabTaskDefinition implements TaskSubmissionProvider, TaskWorkerDefinition, TaskHandler
{
    public const TASK_TYPE = 'official.task.crontab';
    private const RESOURCE_KEY = 'official.task.crontab';
    private const OPERATION = 'execute';
    private const HANDLER_KEY = 'official.task.crontab.execute';

    /** @var null|callable(string,array):void */
    private static $dispatcher = null;

    public function __construct(
        private readonly PDO $pdo,
        private readonly ExecutionContextStore $executionContexts,
        private readonly CurrentExecutionContext $currentExecution,
    ) {
    }

    public function submissionContext(TenantScope $scope, string $traceId): AuthorizedOperationContext
    {
        return $this->authorizedContext($scope->tenantId(), null, null, $traceId);
    }

    public function taskType(): string
    {
        return self::TASK_TYPE;
    }

    public function resourceKey(): string
    {
        return self::RESOURCE_KEY;
    }

    public function operation(): string
    {
        return self::OPERATION;
    }

    public function build(AuthorizedOperationContext $context, array $input): TaskSubmission
    {
        $scheduleId = self::positiveInt($input['schedule_id'] ?? null, 'CRONTAB_TASK_INVALID');
        $contextIdentity = trim((string)($input['context_identity'] ?? ''));
        self::assertContextIdentity($contextIdentity, $context->tenantContext->tenantId, $scheduleId);

        return new TaskSubmission(self::HANDLER_KEY, [
            'schedule_id' => $scheduleId,
            'context_identity' => $contextIdentity,
        ]);
    }

    public function ownerModuleKey(): string
    {
        return 'official.task';
    }

    public function handler(): TaskHandler
    {
        return $this;
    }

    public function reauthorize(VerifiedJobEnvelope $envelope): AuthorizedOperationContext
    {
        if (!hash_equals(self::RESOURCE_KEY, $envelope->resourceKey)
            || !hash_equals(self::OPERATION, $envelope->operation)
            || $envelope->requestedTargets !== []
        ) {
            throw new \RuntimeException('CRONTAB_TASK_AUTHORIZATION_INVALID');
        }

        return $this->authorizedContext(
            $envelope->tenantId,
            $envelope->memberId,
            $envelope->accountId,
            $envelope->traceId,
        );
    }

    public function key(): string
    {
        return self::HANDLER_KEY;
    }

    public function handle(AuthorizedOperationContext $context, JobExecution $execution): void
    {
        $scheduleId = self::positiveInt($execution->payload['schedule_id'] ?? null, 'CRONTAB_TASK_INVALID');
        $contextIdentity = trim((string)($execution->payload['context_identity'] ?? ''));
        self::assertContextIdentity($contextIdentity, $context->tenantContext->tenantId, $scheduleId);
        $scope = TenantScope::fromTrustedContext($context->tenantContext->tenantId, $contextIdentity);
        $item = CrontabTenantRepository::find($scheduleId);
        if ($item === null || (int)$item->status !== CrontabEnum::START) {
            return;
        }

        $command = trim((string)$item->command);
        CrontabCommandService::assertTenantAware($command);
        $moduleKey = CrontabCommandService::moduleKey($command)
            ?? throw new \RuntimeException('CRONTAB_MODULE_UNAVAILABLE');
        $params = ((string)$item->params !== '') ? explode(' ', (string)$item->params) : [];
        $system = new TenantSystemContext(
            $scope->tenantId(),
            'scheduler',
            'crontab.execute',
            $scope->contextIdentity(),
        );

        $this->executionContexts->run(ExecutionContext::system($system), function () use ($scope, $moduleKey, $command, $params): void {
            $modules = new ModuleExecutionBoundary($this->pdo, $this->currentExecution);
            $modules->assertScheduled('official.task');
            $modules->assertScheduled($moduleKey);
            ScheduledTenantContext::run($scope, function () use ($command, $params): void {
                try {
                    if (self::$dispatcher !== null) {
                        (self::$dispatcher)($command, $params);
                        return;
                    }
                    Console::call($command, $params);
                } catch (\Throwable) {
                    throw new RetryableTaskException('CRONTAB_EXECUTION_FAILED');
                }
            });
        });
    }

    /** @param null|callable(string,array):void $dispatcher */
    public static function useDispatcherForTest(?callable $dispatcher): void
    {
        self::$dispatcher = $dispatcher;
    }

    private function authorizedContext(
        int $tenantId,
        ?int $memberId,
        ?int $accountId,
        string $traceId,
    ): AuthorizedOperationContext {
        if ($tenantId < 1 || trim($traceId) === '') {
            throw new \RuntimeException('CRONTAB_TASK_AUTHORIZATION_INVALID');
        }
        $sql = <<<'SQL'
SELECT member.id, member.account_id, member.authorization_revision
FROM pa_tenant_member member
JOIN pa_account account
  ON account.id = member.account_id
 AND account.status = 'active'
JOIN pa_member_role membership
  ON membership.tenant_id = member.tenant_id
 AND membership.tenant_member_id = member.id
JOIN pa_role role
  ON role.tenant_id = membership.tenant_id
 AND role.id = membership.role_id
 AND role.`key` = 'core.tenant-owner'
 AND role.is_builtin = 1
 AND role.status = 'active'
WHERE member.tenant_id = :tenant_id
  AND member.status = 'active'
SQL;
        $bindings = ['tenant_id' => $tenantId];
        if ($memberId !== null || $accountId !== null) {
            if (($memberId ?? 0) < 1 || ($accountId ?? 0) < 1) {
                throw new \RuntimeException('CRONTAB_TASK_AUTHORIZATION_INVALID');
            }
            $sql .= "  AND member.id = :member_id\n  AND member.account_id = :account_id\n";
            $bindings['member_id'] = $memberId;
            $bindings['account_id'] = $accountId;
        }
        $statement = $this->pdo->prepare($sql . "\nORDER BY member.id ASC LIMIT 1");
        $statement->execute($bindings);
        $owner = $statement->fetch(PDO::FETCH_ASSOC);
        if (!is_array($owner)) {
            throw new \RuntimeException('CRONTAB_TENANT_OWNER_UNAVAILABLE');
        }
        $tenant = TenantContext::fromValidatedSession(new ValidatedTenantSession(
            (int)$owner['id'],
            'crontab-' . hash('sha256', $traceId),
            $tenantId,
            (int)$owner['account_id'],
            (int)$owner['id'],
            'task-worker',
            new DateTimeImmutable('now', new DateTimeZone('UTC')),
            (int)$owner['authorization_revision'],
        ), $traceId);

        return AuthorizedOperationContext::fromDecision(AuthorizationDecision::allow(
            $tenant,
            self::RESOURCE_KEY,
            self::OPERATION,
            [],
            hash('sha256', implode("\0", [
                (string)$tenantId,
                (string)$tenant->memberId,
                (string)$tenant->authorizationRevision,
                self::RESOURCE_KEY,
                self::OPERATION,
            ])),
        ));
    }

    private static function positiveInt(mixed $value, string $message): int
    {
        if (!is_int($value) && !(is_string($value) && ctype_digit($value))) {
            throw new \InvalidArgumentException($message);
        }
        $value = (int)$value;
        if ($value < 1) {
            throw new \InvalidArgumentException($message);
        }
        return $value;
    }

    private static function assertContextIdentity(string $identity, int $tenantId, int $scheduleId): void
    {
        if (preg_match('/^crontab:v1:tenant=([1-9][0-9]*):job=([1-9][0-9]*):window=[0-9]+$/D', $identity, $matches) !== 1
            || (int)$matches[1] !== $tenantId
            || (int)$matches[2] !== $scheduleId
        ) {
            throw new \InvalidArgumentException('CRONTAB_TASK_CONTEXT_INVALID');
        }
    }
}
