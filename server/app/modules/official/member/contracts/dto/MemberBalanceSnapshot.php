<?php
declare(strict_types=1);

namespace app\modules\official\member\contracts\dto;

final readonly class MemberBalanceSnapshot
{
    public function __construct(
        public int $memberId,
        public int $balanceCents,
        public int $totalRechargeCents,
    ) {
    }
}
