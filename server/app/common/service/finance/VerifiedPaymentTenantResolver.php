<?php
declare(strict_types=1);

namespace app\common\service\finance;

use app\common\model\finance\RechargeOrder;
use PeanutAdmin\Kernel\Context\TenantSystemContext;

/** Resolves a verified provider event only through the globally unique order number. */
final class VerifiedPaymentTenantResolver
{
    public static function resolve(string $orderSn): TenantSystemContext
    {
        $orderSn = trim($orderSn);
        if ($orderSn === '') {
            throw new \RuntimeException('支付回调订单缺失');
        }
        $owners = RechargeOrder::where('sn', $orderSn)->limit(2)->column('tenant_id');
        if (count($owners) !== 1 || (int)$owners[0] < 1) {
            throw new \RuntimeException('充值订单不存在');
        }
        return FinanceTenantContext::verifiedPayment((int)$owners[0], $orderSn);
    }
}
