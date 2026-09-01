<?php
declare(strict_types=1);

namespace app\Modules\Official\Payment\Infrastructure\Persistence;

use app\common\service\member\AuthenticatedMemberContext;
use app\Modules\Official\Payment\Model\RechargeOrder;
use app\Modules\Official\Payment\Model\RefundLog;
use app\Modules\Official\Payment\Model\RefundRecord;
use PeanutAdmin\Kernel\Tenancy\TenantScope;
use PeanutAdmin\Kernel\Auth\TenantContext;
use PeanutAdmin\Kernel\Context\TenantSystemContext;
use app\common\persistence\ConvertsModelPage;

final class FinanceTenantRepository
{
    use ConvertsModelPage;

    public const PAY_WAY_BALANCE = RechargeOrder::PAY_WAY_BALANCE;
    public const PAY_WAY_WECHAT = RechargeOrder::PAY_WAY_WECHAT;
    public const PAY_WAY_ALIPAY = RechargeOrder::PAY_WAY_ALIPAY;
    public const PAY_STATUS_UNPAID = RechargeOrder::PAY_STATUS_UNPAID;
    public const PAY_STATUS_PAID = RechargeOrder::PAY_STATUS_PAID;
    public const REFUND_STATUS_NONE = RechargeOrder::REFUND_STATUS_NONE;
    public const REFUND_STATUS_STARTED = RechargeOrder::REFUND_STATUS_STARTED;
    public const SCENE_STATUS_ENABLED = \app\Modules\Official\Payment\Model\PaymentScene::STATUS_ENABLED;

    public static function orders(
        AuthenticatedMemberContext|TenantContext|TenantSystemContext|TenantScope $context,
        string $alias = ''
    )
    {
        return $alias === '' ? RechargeOrder::where([]) : RechargeOrder::alias($alias)->where([]);
    }

    public static function records(TenantContext|TenantSystemContext|TenantScope $context, string $alias = '')
    {
        return $alias === '' ? RefundRecord::where([]) : RefundRecord::alias($alias)->where([]);
    }

    public static function logs(TenantContext|TenantSystemContext|TenantScope $context)
    {
        return RefundLog::where([]);
    }

    public static function createOrder(
        AuthenticatedMemberContext|TenantContext $context,
        array $data
    ): RechargeOrder
    {
        unset($data['tenant_id']);
        return RechargeOrder::create($data);
    }

    public static function createRecord(TenantContext $context, array $data): RefundRecord
    {
        unset($data['tenant_id']);
        return RefundRecord::create($data);
    }

    public static function createLog(TenantContext $context, array $data): RefundLog
    {
        unset($data['tenant_id']);
        return RefundLog::create($data);
    }

    public static function nextOrderSn(): string
    {
        return RechargeOrder::generateSn();
    }

    public static function nextPaySn(): string
    {
        return RechargeOrder::generatePaySn();
    }

    public static function nextRefundSn(): string
    {
        return RefundRecord::generateSn();
    }

    public static function nextRefundLogSn(): string
    {
        return RefundLog::generateSn();
    }

    public static function payWayDescription(int $payWay): string
    {
        return \app\Modules\Official\Payment\Model\PaymentScene::getPayWayDesc($payWay);
    }

    public static function supportsPayWay(int $terminal, int $payWay): bool
    {
        return \app\Modules\Official\Payment\Model\PaymentScene::supports($terminal, $payWay);
    }

    /** @return list<array<string,mixed>> */
    public static function paymentScenes(): array
    {
        return \app\Modules\Official\Payment\Model\PaymentScene::field('terminal,pay_way,status,is_default')
            ->order('terminal', 'asc')
            ->order('pay_way', 'asc')
            ->select()
            ->toArray();
    }
}
