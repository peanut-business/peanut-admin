<?php
declare(strict_types=1);

namespace app\Modules\Official\Task\Application;

use app\Modules\Official\Task\Contracts\TaskJobRuntime;
use app\Modules\Official\Task\Contracts\TaskScheduler;
use app\Modules\Official\Task\Contracts\TaskWorkerDefinition;
use PeanutAdmin\Kernel\Tenancy\TenantScope;

final readonly class TaskSchedulerService implements TaskScheduler
{
    /** @var list<TaskWorkerDefinition> */
    private array $definitions;

    public function __construct(
        private TaskJobRuntime $tasks,
        TaskWorkerDefinition ...$definitions,
    ) {
        $this->definitions = $definitions;
    }

    public function runDue(int $now): void
    {
        $tenantIds = CrontabSchedulerService::runDue(
            $now,
            fn(TenantScope $scope, array $item) => $this->tasks->enqueueCrontab(
                $scope,
                (int)$item['id'],
                $scope->contextIdentity(),
            ),
        );
        foreach ($tenantIds as $tenantId) {
            $this->tasks->runTenant($tenantId, $this->workerId(), ...$this->definitions);
        }
    }

    public function start(TenantScope $scope, array $item): void
    {
        $this->tasks->enqueueCrontab($scope, (int)($item['id'] ?? 0), $scope->contextIdentity());
        $this->tasks->runTenant($scope->tenantId(), $this->workerId(), ...$this->definitions);
    }

    private function workerId(): string
    {
        return 'crontab-' . getmypid() . '-' . bin2hex(random_bytes(6));
    }
}
