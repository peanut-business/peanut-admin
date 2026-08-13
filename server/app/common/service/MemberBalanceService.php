<?php
declare(strict_types=1);

namespace app\common\service;

use app\common\enum\AccountLogEnum;
use app\common\logic\AccountLogLogic;
use app\common\model\member\Member;
use app\common\service\member\MemberTenantRepository;
use app\common\service\finance\FinanceTenantContext;
use PeanutAdmin\Kernel\Auth\TenantContext;
use PeanutAdmin\Kernel\Context\TenantSystemContext;

/** 会员余额、兼容镜像和分类流水的唯一写入入口。 */
final class MemberBalanceService
{
    /** 调用方必须已开启包含其领域状态变更的数据库事务。 */
    public static function applyInTransaction(
        TenantContext|TenantSystemContext $context,
        int $memberId,
        int $changeType,
        int $action,
        int $amountCents,
        string $sourceSn = '',
        string $remark = '',
        array $extra = [],
        int $adminId = 0,
        int $rechargeDeltaCents = 0,
        string $insufficientMessage = ''
    ): Member {
        if ($context instanceof TenantSystemContext) {
            FinanceTenantContext::tenantId($context);
        }
        if ($amountCents <= 0) {
            throw new \InvalidArgumentException('调整金额必须大于零');
        }
        if (!in_array($action, [AccountLogEnum::INC, AccountLogEnum::DEC], true)) {
            throw new \InvalidArgumentException('余额变动方向无效');
        }

        /** @var Member $member */
        $member = MemberTenantRepository::members($context)->lock(true)->findOrEmpty($memberId);
        if ($member->isEmpty()) {
            throw new \RuntimeException($insufficientMessage !== '' ? $insufficientMessage : '用户不存在');
        }

        $currentCents = self::moneyToCents((string)$member->getData('user_money'));
        $afterCents = $currentCents + ($action === AccountLogEnum::INC ? $amountCents : -$amountCents);
        if ($afterCents < 0) {
            throw new \RuntimeException(
                $insufficientMessage !== ''
                    ? $insufficientMessage
                    : '用户可用余额仅剩' . (float)$member->getData('user_money')
            );
        }

        $rechargeCents = self::moneyToCents((string)$member->getData('total_recharge_amount'));
        $afterRechargeCents = $rechargeCents + $rechargeDeltaCents;
        if ($afterRechargeCents < 0) {
            throw new \RuntimeException(
                $insufficientMessage !== '' ? $insufficientMessage : '累计充值金额不足'
            );
        }

        $afterMoney = self::centsToMoney($afterCents);
        $member->user_money = $afterMoney;
        $member->balance = $afterMoney;
        if ($rechargeDeltaCents !== 0) {
            $member->total_recharge_amount = self::centsToMoney($afterRechargeCents);
        }
        $member->save();

        if (AccountLogLogic::add(
            $context,
            $memberId,
            $changeType,
            $action,
            $amountCents / 100,
            $sourceSn,
            $remark,
            $extra,
            $adminId
        ) === false) {
            throw new \RuntimeException('账户流水记录失败');
        }

        return $member;
    }

    public static function moneyToCents(int|float|string $amount): int
    {
        return (int)round((float)$amount * 100);
    }

    public static function centsToMoney(int $cents): string
    {
        return number_format($cents / 100, 2, '.', '');
    }
}
