<?php
declare(strict_types=1);

namespace app\modules\official\member\contracts;

use app\modules\official\member\contracts\dto\MemberBalanceMutation;
use app\modules\official\member\contracts\dto\MemberBalanceSnapshot;
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
