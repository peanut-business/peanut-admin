<?php
declare(strict_types=1);

namespace app\common\service\finance;

use app\common\service\member\AuthenticatedMemberContext;
use app\Modules\Official\Payment\Model\RechargeOrder;
use app\Modules\Official\Payment\Model\RefundLog;
use app\Modules\Official\Payment\Model\RefundRecord;
use PeanutAdmin\Kernel\Tenancy\TenantScope;
use PeanutAdmin\Kernel\Auth\TenantContext;
use PeanutAdmin\Kernel\Context\TenantSystemContext;

final class FinanceTenantRepository
{
    public static function orders(
        AuthenticatedMemberContext|TenantContext|TenantSystemContext|TenantScope $context,
        string $alias = ''
    )
    {
        FinanceTenantContext::tenantId($context);
        return $alias === '' ? RechargeOrder::where([]) : RechargeOrder::alias($alias)->where([]);
    }

    public static function records(TenantContext|TenantSystemContext|TenantScope $context, string $alias = '')
    {
        FinanceTenantContext::tenantId($context);
        return $alias === '' ? RefundRecord::where([]) : RefundRecord::alias($alias)->where([]);
    }

    public static function logs(TenantContext|TenantSystemContext|TenantScope $context)
    {
        FinanceTenantContext::tenantId($context);
        return RefundLog::where([]);
    }

    public static function createOrder(
        AuthenticatedMemberContext|TenantContext $context,
        array $data
    ): RechargeOrder
    {
        FinanceTenantContext::tenantId($context);
        unset($data['tenant_id']);
        return RechargeOrder::create($data);
    }

    public static function createRecord(TenantContext $context, array $data): RefundRecord
    {
        FinanceTenantContext::tenantId($context);
        unset($data['tenant_id']);
        return RefundRecord::create($data);
    }

    public static function createLog(TenantContext $context, array $data): RefundLog
    {
        FinanceTenantContext::tenantId($context);
        unset($data['tenant_id']);
        return RefundLog::create($data);
    }
}
