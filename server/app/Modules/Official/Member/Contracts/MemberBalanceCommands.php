<?php
declare(strict_types=1);

namespace app\Modules\Official\Member\Contracts;

use app\Modules\Official\Member\Contracts\Dto\MemberBalanceMutation;
use app\Modules\Official\Member\Contracts\Dto\MemberBalanceSnapshot;
use PeanutAdmin\Kernel\Auth\TenantContext;
use PeanutAdmin\Kernel\Context\TenantSystemContext;

interface MemberBalanceCommands
{
    /**
     * The caller must already own the database transaction containing its
     * domain-state change. This command locks the Tenant-scoped member row,
     * writes the balance change and appends its ledger row in that transaction.
     */
    public function applyInTransaction(
        TenantContext|TenantSystemContext $context,
        MemberBalanceMutation $mutation,
    ): MemberBalanceSnapshot;
}
