<?php
declare(strict_types=1);

namespace app\Modules\Official\Task;

use app\Modules\Official\Task\Application\PdoTaskJobRuntime;
use app\Modules\Official\Task\Application\TaskSchedulerService;
use app\Modules\Official\Task\Contracts\TaskJobRuntime;
use app\Modules\Official\Task\Contracts\TaskScheduler;
use app\Modules\Official\Task\Contracts\TaskWorkerDefinition;
use app\common\execution\CurrentExecutionContext;
use app\common\execution\ExecutionContextStore;
use PeanutAdmin\Kernel\Module\ModuleProvider as ModuleProviderContract;
use PDO;

final class ModuleProvider implements ModuleProviderContract
{
    public function moduleKey(): string
    {
        return 'official.task';
    }

    public function scheduler(TaskJobRuntime $tasks, TaskWorkerDefinition ...$definitions): TaskScheduler
    {
        return new TaskSchedulerService($tasks, ...$definitions);
    }

    public function jobs(PDO $pdo, string $signingKey): TaskJobRuntime
    {
        return new PdoTaskJobRuntime(
            $pdo,
            $signingKey,
            app(ExecutionContextStore::class),
            app(CurrentExecutionContext::class),
        );
    }
}
