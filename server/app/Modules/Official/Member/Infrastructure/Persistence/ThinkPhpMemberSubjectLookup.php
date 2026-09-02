<?php
declare(strict_types=1);

namespace app\Modules\Official\Member\Infrastructure\Persistence;

use app\Modules\Official\Member\Contracts\MemberSubjectLookup;
use app\Modules\Official\Member\Model\Member;
use app\common\tenancy\PlatformTenantDataGateway;

final readonly class ThinkPhpMemberSubjectLookup implements MemberSubjectLookup
{
    public function __construct(private PlatformTenantDataGateway $tenantData)
    {
    }

    public function tenantId(int $memberId): ?int
    {
        if ($memberId < 1) {
            return null;
        }
        $member = $this->tenantData
            ->query(Member::class, 'api.member-auth', 'resolve-tenant-context')
            ->where('id', $memberId)
            ->where('status', 1)
            ->whereNull('delete_time')
            ->field(['id', 'tenant_id'])
            ->find();
        if ($member === null || (int)$member->getData('id') !== $memberId) {
            return null;
        }
        $tenantId = (int)$member->getData('tenant_id');
        return $tenantId > 0 ? $tenantId : null;
    }
}
