<?php
declare(strict_types=1);

namespace app\Modules\Official\Member\Application;

use app\Modules\Official\Member\Contracts\Dto\MemberBalanceMutation;
use app\Modules\Official\Member\Contracts\Dto\MemberBalanceSnapshot;
use app\Modules\Official\Member\Contracts\MemberBalanceCommands;
use app\common\service\MemberBalanceService;
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
            MemberBalanceService::moneyToCents((string)$member->getData('user_money')),
            MemberBalanceService::moneyToCents((string)$member->getData('total_recharge_amount')),
        );
    }
}
