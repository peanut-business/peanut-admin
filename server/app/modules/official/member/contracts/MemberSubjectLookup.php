<?php
declare(strict_types=1);

namespace app\modules\official\member\contracts;

/** Resolves the owning Tenant for an active member authentication subject. */
interface MemberSubjectLookup
{
    public function tenantId(int $memberId): ?int;
}
