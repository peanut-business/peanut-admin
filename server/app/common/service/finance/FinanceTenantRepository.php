<?php
declare(strict_types=1);

namespace app\common\service\finance;

use app\common\model\finance\RechargeOrder;
use app\common\model\refund\RefundLog;
use app\common\model\refund\RefundRecord;
use app\common\service\tenant\TenantScope;
use PeanutAdmin\Kernel\Auth\TenantContext;
use PeanutAdmin\Kernel\Context\TenantSystemContext;

final class FinanceTenantRepository
{
    public static function orders(TenantContext|TenantSystemContext|TenantScope $context, string $alias = '')
    {
        $tenantId = FinanceTenantContext::tenantId($context);
        return $alias === ''
            ? RechargeOrder::where('tenant_id', $tenantId)
            : RechargeOrder::alias($alias)->where($alias . '.tenant_id', $tenantId);
    }

    public static function records(TenantContext|TenantSystemContext|TenantScope $context, string $alias = '')
    {
        $tenantId = FinanceTenantContext::tenantId($context);
        return $alias === ''
            ? RefundRecord::where('tenant_id', $tenantId)
            : RefundRecord::alias($alias)->where($alias . '.tenant_id', $tenantId);
    }

    public static function logs(TenantContext|TenantSystemContext|TenantScope $context)
    {
        return RefundLog::where('tenant_id', FinanceTenantContext::tenantId($context));
    }

    public static function createOrder(TenantContext $context, array $data): RechargeOrder
    {
        unset($data['tenant_id']);
        return RechargeOrder::create(['tenant_id' => FinanceTenantContext::tenantId($context)] + $data);
    }

    public static function createRecord(TenantContext $context, array $data): RefundRecord
    {
        unset($data['tenant_id']);
        return RefundRecord::create(['tenant_id' => FinanceTenantContext::tenantId($context)] + $data);
    }

    public static function createLog(TenantContext $context, array $data): RefundLog
    {
        unset($data['tenant_id']);
        return RefundLog::create(['tenant_id' => FinanceTenantContext::tenantId($context)] + $data);
    }
}
