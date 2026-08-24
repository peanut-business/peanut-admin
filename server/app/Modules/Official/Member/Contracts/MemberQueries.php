<?php
declare(strict_types=1);

namespace app\Modules\Official\Member\Contracts;

use app\Modules\Official\Member\Contracts\Dto\MemberBalanceLogPage;
use app\Modules\Official\Member\Contracts\Dto\MemberBalanceSnapshot;
use app\common\service\member\AuthenticatedMemberContext;
use PeanutAdmin\Kernel\Auth\TenantContext;
use PeanutAdmin\Kernel\Context\TenantSystemContext;

interface MemberQueries
{
    public function balanceSnapshot(
        AuthenticatedMemberContext|TenantContext|TenantSystemContext $context,
        int $memberId,
    ): ?MemberBalanceSnapshot;

    public function balanceLogsForMember(
        AuthenticatedMemberContext $context,
        int $memberId,
        int $page,
        int $pageSize,
    ): MemberBalanceLogPage;
}
