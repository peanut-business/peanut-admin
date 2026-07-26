<?php
declare(strict_types=1);

namespace app\command;

use app\common\enum\CrontabEnum;
use app\common\model\Crontab as CrontabModel;
use Cron\CronExpression;
use think\console\Command;
use think\console\Input;
use think\console\Output;
use think\facade\Console;

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
        $lists = CrontabModel::where('status', CrontabEnum::START)->select()->toArray();
        if (empty($lists)) {
            return 0;
        }

        $now = time();
        foreach ($lists as $item) {
            // 首次运行：仅登记下次预期时间，不立即执行
            if (empty($item['last_time'])) {
                CrontabModel::where('id', $item['id'])->update(['last_time' => $now]);
                continue;
            }

            try {
                $nextTime = (new CronExpression($item['expression']))
                    ->getNextRunDate(date('Y-m-d H:i:s', (int) $item['last_time']))
                    ->getTimestamp();
            } catch (\Throwable $e) {
                // 表达式非法：标记错误并停止
                CrontabModel::where('id', $item['id'])->update([
                    'error'  => '运行规则错误：' . $e->getMessage(),
                    'status' => CrontabEnum::ERROR,
                ]);
                continue;
            }

            if ($nextTime > $now) {
                continue; // 未到时间
            }

            self::start($item);
        }

        return 0;
    }

    /** 执行单个任务，记录耗时与错误 */
    public static function start(array $item): void
    {
        $startTime = microtime(true);
        try {
            $params = ($item['params'] !== '') ? explode(' ', $item['params']) : [];
            Console::call($item['command'], $params);
            CrontabModel::where('id', $item['id'])->update(['error' => '']);
        } catch (\Throwable $e) {
            CrontabModel::where('id', $item['id'])->update([
                'error'  => $e->getMessage(),
                'status' => CrontabEnum::ERROR,
            ]);
        } finally {
            $useTime = round(microtime(true) - $startTime, 2);
            CrontabModel::where('id', $item['id'])->update([
                'last_time' => time(),
                'time'      => $useTime,
                'max_time'  => max($useTime, (float) $item['max_time']),
            ]);
        }
    }
}
