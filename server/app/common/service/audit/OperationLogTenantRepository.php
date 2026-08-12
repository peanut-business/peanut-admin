<?php
declare(strict_types=1);

namespace app\common\service\audit;

use app\common\model\log\OperationLog;
use PeanutAdmin\Kernel\Auth\TenantContext;

final class OperationLogTenantRepository
{
    public static function query(TenantContext $context)
    {
        return OperationLog::where('tenant_id', OperationLogTenantContext::tenantId($context));
    }

    public static function create(TenantContext $context, array $data): OperationLog
    {
        unset($data['tenant_id'], $data['request_id']);
        return OperationLog::create([
            'tenant_id' => OperationLogTenantContext::tenantId($context),
            'request_id' => $context->requestId,
        ] + $data);
    }

    public static function detail(TenantContext $context, int $id): array
    {
        $row = self::query($context)->where('id', $id)->find();
        if ($row === null) {
            throw new \InvalidArgumentException('操作日志不存在');
        }
        return $row->toArray();
    }
}

