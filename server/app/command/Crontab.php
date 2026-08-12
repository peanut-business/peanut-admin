<?php
declare(strict_types=1);

namespace app\command;

use think\console\Command;
use think\console\Input;
use think\console\Output;
use think\facade\Db;
use app\common\service\crontab\CrontabSchedulerService;
use app\common\service\tenant\TenantScope;

/**
 * 定时任务调度器。
 * 由系统 cron 每分钟调用一次：`* * * * * cd /path/to/server && php think crontab`
 * 每次调用扫描所有「运行中」任务，比对 cron 表达式，派发到期的 console 命令。
 */
class Crontab extends Command
{
    protected function configure()
    {
        $this->setName('crontab')->setDescription('定时任务调度器');
    }

    protected function execute(Input $input, Output $output)
    {
        if (!self::acquireSchedulerLock()) {
            return 0;
        }
        try {
            CrontabSchedulerService::runDue(time());
        } finally {
            self::releaseSchedulerLock();
        }

        return 0;
    }

    /** Compatibility entry for explicit trusted scheduler callers. */
    public static function start(TenantScope $scope, array $item): void
    {
        CrontabSchedulerService::start($scope, $item);
    }

    private static function acquireSchedulerLock(): bool
    {
        $rows = Db::query("SELECT GET_LOCK('peanut:crontab:scheduler', 0) AS acquired");
        return (int)($rows[0]['acquired'] ?? 0) === 1;
    }

    private static function releaseSchedulerLock(): void
    {
        try {
            Db::query("SELECT RELEASE_LOCK('peanut:crontab:scheduler')");
        } catch (\Throwable) {
        }
    }
}
