<?php
declare(strict_types=1);

namespace app\adminapi\logic\crontab;

use app\common\enum\CrontabEnum;
use app\common\logic\BaseLogic;
use app\common\service\crontab\CrontabTenantRepository;
use Cron\CronExpression;
use app\common\service\CrontabCommandService;
use app\common\support\PaginationInput;
use think\facade\Db;
use PeanutAdmin\Kernel\Auth\TenantContext;

/**
 * 定时任务逻辑层
 */
class CrontabLogic extends BaseLogic
{
    /** 分页列表：支持 name(模糊) / status 过滤 */
    public static function lists(TenantContext $context, array $params): array
    {
        self::clearError();
        $where = [];
        if (!empty($params['name'])) {
            $where[] = ['name', 'like', '%' . $params['name'] . '%'];
        }
        if (isset($params['status']) && $params['status'] !== '') {
            $where[] = ['status', '=', (int) $params['status']];
        }

        $pagination = PaginationInput::from($params);

        $count = CrontabTenantRepository::schedules($context)->where($where)->count();
        $lists = CrontabTenantRepository::schedules($context)->where($where)
            ->append(['type_desc', 'status_desc'])
            ->order(['id' => 'desc'])
            ->page($pagination->page, $pagination->pageSize)
            ->select()
            ->toArray();

        $pageNo = $pagination->page;
        $pageSize = $pagination->pageSize;
        return compact('lists', 'count', 'pageNo', 'pageSize');
    }

    public static function detail(TenantContext $context, int $id): array
    {
        self::clearError();
        return CrontabTenantRepository::find($context, $id)?->toArray() ?? [];
    }

    public static function add(TenantContext $context, array $params): bool
    {
        self::clearError();
        try {
            CrontabCommandService::assertAllowed(trim((string)$params['command']));
            CrontabTenantRepository::create($context, [
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
            return self::fail($e);
        }
    }

    public static function edit(TenantContext $context, array $params): bool
    {
        self::clearError();
        try {
            CrontabCommandService::assertAllowed(trim((string)$params['command']));
            Db::transaction(function () use ($context, $params): void {
                $crontab = CrontabTenantRepository::schedules($context)
                    ->where('id', (int)$params['id'])->lock(true)->findOrEmpty();
                if ($crontab->isEmpty()) {
                    throw new \RuntimeException('定时任务不存在');
                }
                $crontab->save([
                    'name' => (string)$params['name'],
                    'type' => (int)$params['type'],
                    'command' => trim((string)$params['command']),
                    'params' => (string)($params['params'] ?? ''),
                    'status' => (int)$params['status'],
                    'expression' => (string)$params['expression'],
                    'sort' => (int)($params['sort'] ?? 0),
                    'remark' => (string)($params['remark'] ?? ''),
                ]);
            });
            return true;
        } catch (\Throwable $e) {
            return self::fail($e);
        }
    }

    public static function delete(TenantContext $context, int $id): bool
    {
        self::clearError();
        $crontab = CrontabTenantRepository::find($context, $id);
        if ($crontab === null) {
            self::setError('定时任务不存在');
            return false;
        }
        $crontab->delete();
        return true;
    }

    /** 运行 / 停止 */
    public static function operate(TenantContext $context, int $id, string $operate): bool
    {
        self::clearError();
        try {
            $crontab = CrontabTenantRepository::find($context, $id);
            if ($crontab === null) {
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
            return self::fail($e);
        }
    }

    /** 预览 cron 表达式未来 5 次执行时间 */
    public static function expression(string $expression): array
    {
        self::clearError();
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
