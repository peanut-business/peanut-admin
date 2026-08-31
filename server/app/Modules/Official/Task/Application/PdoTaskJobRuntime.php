<?php
declare(strict_types=1);

namespace app\Modules\Official\Task\Application;

use app\Modules\Official\Task\Contracts\TaskJobRuntime;
use app\Modules\Official\Task\Contracts\TaskWorkerDefinition;
use app\common\service\async\ModuleAwareTaskHandler;
use app\common\persistence\CoreTenantRepositoryFactory;
use app\common\execution\CurrentExecutionContext;
use app\common\execution\ExecutionContextStore;
use app\common\service\module\ModuleExecutionBoundary;
use PeanutAdmin\Kernel\Async\JobHandlerAdapter;
use PeanutAdmin\Kernel\Async\TrustedEnvelopeCodec;
use PeanutAdmin\TaskJob\Application\TaskJobService;
use PeanutAdmin\TaskJob\Execution\LocalWorker;
use PeanutAdmin\TaskJob\Execution\TaskHandlerRegistry;
use PeanutAdmin\TaskJob\Persistence\PdoTaskJobRepository;
use PeanutAdmin\TaskJob\Submission\TaskSubmissionProvider;
use PeanutAdmin\TaskJob\Submission\TaskSubmissionRegistry;
use PeanutAdmin\TaskJob\Submission\TrustedJobPublisher;
use PeanutAdmin\Kernel\Tenancy\TenantScope;
use PDO;

final readonly class PdoTaskJobRuntime implements TaskJobRuntime
{
    public function __construct(
        private PDO $pdo,
        private string $signingKey,
        private ExecutionContextStore $executionContexts,
        private CurrentExecutionContext $currentExecution,
    ) {
        if (strlen($this->signingKey) < 32) {
            throw new \RuntimeException('ASYNC_SIGNING_KEY_INVALID');
        }
    }

    public function publisher(TaskSubmissionProvider ...$providers): TrustedJobPublisher
    {
        return new TrustedJobPublisher(
            $this->repository(),
            new TaskSubmissionRegistry($providers),
            $this->envelopes(),
        );
    }

    public function jobs(): TaskJobService
    {
        return new TaskJobService($this->repository());
    }

    public function enqueueCrontab(TenantScope $scope, int $scheduleId, string $contextIdentity): void
    {
        $definition = $this->crontabs();
        $this->publisher($definition)->publish(
            $definition->submissionContext($scope, $contextIdentity),
            CrontabTaskDefinition::TASK_TYPE,
            ['schedule_id' => $scheduleId, 'context_identity' => $contextIdentity],
            'crontab:' . hash('sha256', $contextIdentity),
        );
    }

    public function runTenant(int $tenantId, string $workerId, TaskWorkerDefinition ...$definitions): int
    {
        if ($tenantId < 1) {
            throw new \RuntimeException('ASYNC_TENANT_INVALID');
        }

        $definitions[] = $this->crontabs();
        $handlers = [];
        foreach ($definitions as $definition) {
            $handlers[] = new ModuleAwareTaskHandler(
                new ModuleExecutionBoundary($this->pdo, $this->currentExecution),
                $this->executionContexts,
                $definition->ownerModuleKey(),
                $definition->handler(),
            );
        }
        $worker = new LocalWorker(
            $tenantId,
            $workerId,
            $this->repository(),
            new TaskHandlerRegistry($handlers),
            new JobHandlerAdapter($this->envelopes(), new TaskAuthorizationRouter($definitions)),
        );

        $processed = 0;
        $limit = min(1000, max(1, (int)config('async.worker_limit', 25)));
        while ($processed < $limit && $worker->runOnce() !== null) {
            ++$processed;
        }
        return $processed;
    }

    private function repository(): PdoTaskJobRepository
    {
        return (new CoreTenantRepositoryFactory($this->pdo))->taskJobs();
    }

    private function envelopes(): TrustedEnvelopeCodec
    {
        return new TrustedEnvelopeCodec($this->signingKey);
    }

    private function crontabs(): CrontabTaskDefinition
    {
        return new CrontabTaskDefinition($this->pdo, $this->executionContexts, $this->currentExecution);
    }
}
