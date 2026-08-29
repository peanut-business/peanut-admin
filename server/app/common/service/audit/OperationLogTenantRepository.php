<?php
declare(strict_types=1);

namespace app\common\service\audit;

use app\common\model\log\OperationLog;
use app\common\execution\CurrentExecutionContext;

final class OperationLogTenantRepository
{
    public static function query()
    {
        return OperationLog::where([]);
    }

    public static function create(array $data): OperationLog
    {
        unset($data['tenant_id'], $data['request_id']);
        $requestId = app(CurrentExecutionContext::class)->requestId();
        return OperationLog::create([
            'request_id' => $requestId,
        ] + $data);
    }

    public static function detail(int $id): array
    {
        $row = self::query()->where('id', $id)->find();
        if ($row === null) {
            throw new \InvalidArgumentException('操作日志不存在');
        }
        return $row->toArray();
    }
}
