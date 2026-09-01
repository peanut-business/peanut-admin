<?php
declare(strict_types=1);

namespace app\Modules\Official\Member\Contracts;

/** Resolves the owning Tenant for an active member authentication subject. */
interface MemberSubjectLookup
{
    public function tenantId(int $memberId): ?int;
}
