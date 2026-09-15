<?php
declare(strict_types=1);

namespace app\modules\official\member\services;

use app\modules\official\member\contracts\dto\MemberBalanceMutation;
use app\modules\official\member\contracts\dto\MemberBalanceSnapshot;
use app\modules\official\member\contracts\MemberBalanceCommands;
use app\common\value\Money;
use PeanutAdmin\Kernel\Auth\TenantContext;
use PeanutAdmin\Kernel\Context\TenantSystemContext;

final class MemberBalanceContractService implements MemberBalanceCommands
{
    public function applyInTransaction(
        TenantContext|TenantSystemContext $context,
        MemberBalanceMutation $mutation,
    ): MemberBalanceSnapshot {
        $member = MemberBalanceService::applyInTransaction(
            $context,
            $mutation->memberId,
            $mutation->changeType,
            $mutation->action,
            $mutation->amountCents,
            $mutation->sourceSn,
            $mutation->remark,
            $mutation->extra,
            $mutation->adminId,
            $mutation->rechargeDeltaCents,
            $mutation->insufficientMessage,
        );

        return new MemberBalanceSnapshot(
            (int)$member->id,
            Money::toCents((string)$member->getData('user_money')),
            Money::toCents((string)$member->getData('total_recharge_amount')),
        );
    }
}
