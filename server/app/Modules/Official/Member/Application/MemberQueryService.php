<?php
declare(strict_types=1);

namespace app\Modules\Official\Member\Application;

use app\Modules\Official\Member\Contracts\Dto\MemberBalanceLogPage;
use app\Modules\Official\Member\Contracts\Dto\MemberBalanceSnapshot;
use app\Modules\Official\Member\Contracts\MemberQueries;
use app\common\service\MemberBalanceService;
use app\common\service\member\AuthenticatedMemberContext;
use app\common\service\member\MemberTenantRepository;
use PeanutAdmin\Kernel\Auth\TenantContext;
use PeanutAdmin\Kernel\Context\TenantSystemContext;

final class MemberQueryService implements MemberQueries
{
    public function memberFields(
        AuthenticatedMemberContext|TenantContext|TenantSystemContext $context,
        int $memberId,
        array $fields,
    ): array {
        $member = MemberTenantRepository::members($context)
            ->field($fields)->findOrEmpty($memberId);
        if ($member->isEmpty()) {
            return [];
        }
        $data = $member->toArray();
        if (in_array('password', $fields, true)) {
            $data['password'] = (string)$member->getData('password');
        }
        return $data;
    }

    public function tags(TenantContext|TenantSystemContext $context): array
    {
        return MemberTenantRepository::tags($context)->order('id', 'desc')->select()->toArray();
    }

    public function balanceSnapshot(
        AuthenticatedMemberContext|TenantContext|TenantSystemContext $context,
        int $memberId,
    ): ?MemberBalanceSnapshot {
        $member = MemberTenantRepository::members($context)->findOrEmpty($memberId);
        if ($member->isEmpty()) {
            return null;
        }

        return new MemberBalanceSnapshot(
            (int)$member->id,
            MemberBalanceService::moneyToCents((string)$member->getData('user_money')),
            MemberBalanceService::moneyToCents((string)$member->getData('total_recharge_amount')),
        );
    }

    public function balanceLogsForMember(
        AuthenticatedMemberContext $context,
        int $memberId,
        int $page,
        int $pageSize,
    ): MemberBalanceLogPage {
        $page = max(1, $page);
        $pageSize = max(1, $pageSize);
        $query = MemberTenantRepository::balanceLogs($context)->where('member_id', $memberId);
        $total = $query->count();

        return new MemberBalanceLogPage(
            $query->order('id', 'desc')->page($page, $pageSize)->select()->toArray(),
            $total,
            $page,
            $pageSize,
        );
    }
}
