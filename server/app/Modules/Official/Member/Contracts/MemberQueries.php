<?php
declare(strict_types=1);

namespace app\Modules\Official\Member\Contracts;

use app\Modules\Official\Member\Contracts\Dto\MemberBalanceSnapshot;
use app\common\http\PageResult;
use app\common\service\member\AuthenticatedMemberContext;
use PeanutAdmin\Kernel\Auth\TenantContext;
use PeanutAdmin\Kernel\Context\TenantSystemContext;

interface MemberQueries
{
    /**
     * Returns Tenant-scoped member fields without resolving file URLs.
     * Consumers keep presentation and storage URL handling at their boundary.
     */
    public function memberFields(
        AuthenticatedMemberContext|TenantContext|TenantSystemContext $context,
        int $memberId,
        array $fields,
    ): array;

    /** @return list<array<string, mixed>> */
    public function tags(TenantContext|TenantSystemContext $context): array;

    public function balanceSnapshot(
        AuthenticatedMemberContext|TenantContext|TenantSystemContext $context,
        int $memberId,
    ): ?MemberBalanceSnapshot;

    public function balanceLogsForCurrentMember(int $page, int $pageSize): PageResult;
}
