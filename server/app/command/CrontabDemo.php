<?php
declare(strict_types=1);

namespace app\command;

use think\console\Command;
use think\console\Input;
use think\console\Output;
use think\facade\Log;

/**
 * 定时任务演示命令。
 * 供「定时任务」模块开箱验证：被调度时向 runtime 日志写入一条记录。
 * 命令名填 `crontab:demo`，可用于确认调度链路是否打通。
 */
class CrontabDemo extends Command
{
    protected function configure()
    {
        $this->setName('crontab:demo')->setDescription('定时任务演示命令');
    }

    protected function execute(Input $input, Output $output)
    {
        $msg = '[crontab:demo] executed at ' . date('Y-m-d H:i:s');
        Log::info($msg);
        $output->writeln($msg);
        return 0;
    }
}
