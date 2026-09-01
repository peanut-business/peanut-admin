<?php
declare(strict_types=1);

namespace app\command;

use app\common\execution\DatabaseContextualCommand;
use app\common\service\async\TaskImportExportRuntimeFactory;
use app\Modules\Official\Task\Contracts\TaskScheduler;
use think\console\Input;
use think\console\Output;
use PeanutAdmin\Kernel\Tenancy\TenantScope;

/**
 * 定时任务调度器。
 * 由系统 cron 每分钟调用一次：`* * * * * cd /path/to/server && php think crontab`
 * 每次调用扫描所有「运行中」任务，比对 cron 表达式，派发到期的 console 命令。
 */
class Crontab extends DatabaseContextualCommand
{
    protected function configure()
    {
        $this->setName('crontab')->setDescription('定时任务调度器');
    }

    protected function handle(Input $input, Output $output): int
    {
        if (!$this->acquireSchedulerLock()) {
            return 0;
        }
        try {
            $this->scheduler()->runDue(time());
        } finally {
            $this->releaseSchedulerLock();
        }

        return 0;
    }

    /** Compatibility entry for explicit trusted scheduler callers. */
    public function start(TenantScope $scope, array $item): void
    {
        $this->scheduler()->start($scope, $item);
    }

    private function scheduler(): TaskScheduler
    {
        return TaskImportExportRuntimeFactory::scheduler($this->database());
    }

    private function acquireSchedulerLock(): bool
    {
        $statement = $this->database()->query("SELECT GET_LOCK('peanut:crontab:scheduler', 0)");
        return (int)$statement->fetchColumn() === 1;
    }

    private function releaseSchedulerLock(): void
    {
        try {
            $this->database()->query("SELECT RELEASE_LOCK('peanut:crontab:scheduler')");
        } catch (\Throwable) {
        }
    }
}
