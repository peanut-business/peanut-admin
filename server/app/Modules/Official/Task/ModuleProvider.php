<?php
declare(strict_types=1);

namespace app\Modules\Official\Task;

use app\Modules\Official\Task\Application\CrontabSchedulerService;
use app\Modules\Official\Task\Application\TaskSchedulerService;
use app\Modules\Official\Task\Application\TaskBootstrapService;
use app\Modules\Official\Task\Infrastructure\Runtime\ThinkPhpTaskJobRuntime;
use app\Modules\Official\Task\Contracts\TaskJobRuntime;
use app\Modules\Official\Task\Contracts\TaskScheduler;
use app\Modules\Official\Task\Contracts\TaskBootstrapCommands;
use app\Modules\Official\Task\Contracts\TaskWorkerDefinition;
use app\common\execution\CurrentExecutionContext;
use app\common\execution\ExecutionContextStore;
use app\common\service\module\ModuleExecutionBoundary;
use app\common\service\org\AdminDirectoryQuery;
use app\common\services\CrontabCommandService;
use Closure;
use app\common\persistence\CoreTenantRepositoryFactory;
use PeanutAdmin\Kernel\Module\ModuleProvider as ModuleProviderContract;
use PeanutAdmin\Kernel\Persistence\ThinkPhp\ThinkPhpTransactionManager;
use think\App;
use think\db\PDOConnection;

final class ModuleProvider implements ModuleProviderContract
{
    public function moduleKey(): string
    {
        return 'official.task';
    }

    public function scheduler(
        TaskJobRuntime $tasks,
        CrontabSchedulerService $crontabs,
        TaskWorkerDefinition ...$definitions,
    ): TaskScheduler
    {
        return new TaskSchedulerService($tasks, $crontabs, ...$definitions);
    }

    public function jobs(
        PDOConnection $connection,
        string $signingKey,
        ExecutionContextStore $executionContexts,
        CurrentExecutionContext $currentExecution,
        AdminDirectoryQuery $adminDirectory,
        ModuleExecutionBoundary $modules,
        CrontabCommandService $commands,
        Closure $dispatch,
        int $workerLimit,
    ): TaskJobRuntime
    {
        $transactions = new ThinkPhpTransactionManager($connection);

        return new ThinkPhpTaskJobRuntime(
            (new CoreTenantRepositoryFactory($connection->connect()))->taskJobs($connection),
            $transactions,
            $signingKey,
            $executionContexts,
            $currentExecution,
            $adminDirectory,
            $modules,
            $commands,
            $dispatch,
            $workerLimit,
        );
    }

    public function bindings(): array
    {
        return [
            TaskJobRuntime::class => fn(App $app): TaskJobRuntime => $this->jobs(
                $app->make(PDOConnection::class),
                (string)$app->config->get('async.signing_key', ''),
                $app->make(ExecutionContextStore::class),
                $app->make(CurrentExecutionContext::class),
                $app->make(AdminDirectoryQuery::class),
                $app->make(ModuleExecutionBoundary::class),
                $app->make(CrontabCommandService::class),
                Closure::fromCallable([$app->make('console'), 'call']),
                (int)$app->config->get('async.worker_limit', 25),
            ),
            TaskBootstrapCommands::class => TaskBootstrapService::class,
            TaskScheduler::class => fn(App $app): TaskScheduler => $this->scheduler(
                $app->make(TaskJobRuntime::class),
                $app->make(CrontabSchedulerService::class),
            ),
        ];
    }
}
