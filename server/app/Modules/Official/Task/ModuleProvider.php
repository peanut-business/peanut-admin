<?php
declare(strict_types=1);

namespace app\Modules\Official\Task;

use app\common\composition\ModuleBindingContributor;
use app\Modules\Official\Task\Application\PdoTaskJobRuntime;
use app\Modules\Official\Task\Application\TaskSchedulerService;
use app\Modules\Official\Task\Contracts\TaskJobRuntime;
use app\Modules\Official\Task\Contracts\TaskScheduler;
use app\Modules\Official\Task\Contracts\TaskWorkerDefinition;
use app\common\execution\CurrentExecutionContext;
use app\common\execution\ExecutionContextStore;
use PeanutAdmin\Kernel\Module\ModuleProvider as ModuleProviderContract;
use PDO;
use think\App;

final class ModuleProvider implements ModuleProviderContract, ModuleBindingContributor
{
    public function moduleKey(): string
    {
        return 'official.task';
    }

    public function scheduler(TaskJobRuntime $tasks, TaskWorkerDefinition ...$definitions): TaskScheduler
    {
        return new TaskSchedulerService($tasks, ...$definitions);
    }

    public function jobs(
        PDO $pdo,
        string $signingKey,
        ExecutionContextStore $executionContexts,
        CurrentExecutionContext $currentExecution,
    ): TaskJobRuntime
    {
        return new PdoTaskJobRuntime(
            $pdo,
            $signingKey,
            $executionContexts,
            $currentExecution,
        );
    }

    public function bindings(): array
    {
        return [
            TaskJobRuntime::class => fn(App $app): TaskJobRuntime => $this->jobs(
                $app->make(PDO::class),
                (string)$app->config->get('async.signing_key', ''),
                $app->make(ExecutionContextStore::class),
                $app->make(CurrentExecutionContext::class),
            ),
            TaskScheduler::class => fn(App $app): TaskScheduler => $this->scheduler(
                $app->make(TaskJobRuntime::class),
            ),
        ];
    }
}
