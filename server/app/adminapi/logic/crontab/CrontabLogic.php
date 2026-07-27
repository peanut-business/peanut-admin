<?php
declare(strict_types=1);

namespace app\adminapi\logic\crontab;

use app\common\enum\CrontabEnum;
use app\common\logic\BaseLogic;
use app\common\model\Crontab;
use Cron\CronExpression;

/**
 * 定时任务逻辑层
 */
class CrontabLogic extends BaseLogic
{
    /** 分页列表：支持 name(模糊) / status 过滤 */
    public static function lists(array $params): array
    {
        $where = [];
        if (!empty($params['name'])) {
            $where[] = ['name', 'like', '%' . $params['name'] . '%'];
        }
        if (isset($params['status']) && $params['status'] !== '') {
            $where[] = ['status', '=', (int) $params['status']];
        }

        $pageNo   = max(1, (int) ($params['page_no'] ?? 1));
        $pageSize = min(100, max(1, (int) ($params['page_size'] ?? 15)));

        $count = Crontab::where($where)->count();
        $lists = Crontab::where($where)
            ->append(['type_desc', 'status_desc'])
            ->order(['id' => 'desc'])
            ->page($pageNo, $pageSize)
            ->select()
            ->toArray();

        return compact('lists', 'count', 'pageNo', 'pageSize');
    }

    public static function detail(int $id): array
    {
        return Crontab::findOrEmpty($id)->toArray();
    }

    public static function add(array $params): bool
    {
        try {
            Crontab::create([
                'name'       => (string) $params['name'],
                'type'       => (int) $params['type'],
                'command'    => (string) $params['command'],
                'params'     => (string) ($params['params'] ?? ''),
                'status'     => (int) $params['status'],
                'expression' => (string) $params['expression'],
                'sort'       => (int) ($params['sort'] ?? 0),
                'remark'     => (string) ($params['remark'] ?? ''),
                'last_time'  => time(),
            ]);
            return true;
        } catch (\Throwable $e) {
            self::setError($e->getMessage());
            return false;
        }
    }

    public static function edit(array $params): bool
    {
        try {
            Crontab::update([
                'id'         => (int) $params['id'],
                'name'       => (string) $params['name'],
                'type'       => (int) $params['type'],
                'command'    => (string) $params['command'],
                'params'     => (string) ($params['params'] ?? ''),
                'status'     => (int) $params['status'],
                'expression' => (string) $params['expression'],
                'sort'       => (int) ($params['sort'] ?? 0),
                'remark'     => (string) ($params['remark'] ?? ''),
            ]);
            return true;
        } catch (\Throwable $e) {
            self::setError($e->getMessage());
            return false;
        }
    }

    public static function delete(int $id): void
    {
        Crontab::destroy($id);
    }

    /** 运行 / 停止 */
    public static function operate(int $id, string $operate): bool
    {
        try {
            $crontab = Crontab::findOrEmpty($id);
            if ($crontab->isEmpty()) {
                throw new \Exception('定时任务不存在');
            }
            if ($operate === 'start') {
                $crontab->status = CrontabEnum::START;
                $crontab->error  = ''; // 重新启动时清除历史错误
            } elseif ($operate === 'stop') {
                $crontab->status = CrontabEnum::STOP;
            } else {
                throw new \Exception('操作类型错误');
            }
            $crontab->save();
            return true;
        } catch (\Throwable $e) {
            self::setError($e->getMessage());
            return false;
        }
    }

    /** 预览 cron 表达式未来 5 次执行时间 */
    public static function expression(string $expression): array
    {
        try {
            $cron   = new CronExpression($expression);
            $result = $cron->getMultipleRunDates(5);
            $lists  = [];
            foreach ($result as $k => $date) {
                $lists[] = [
                    'time' => $k + 1,
                    'date' => $date->format('Y-m-d H:i:s'),
                ];
            }
            return $lists;
        } catch (\Throwable $e) {
            self::setError($e->getMessage());
            return [];
        }
    }
}
