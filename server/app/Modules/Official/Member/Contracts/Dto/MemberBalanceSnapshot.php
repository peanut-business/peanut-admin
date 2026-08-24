<?php
declare(strict_types=1);

namespace app\Modules\Official\Member\Contracts\Dto;

final readonly class MemberBalanceSnapshot
{
    public function __construct(
        public int $memberId,
        public int $balanceCents,
        public int $totalRechargeCents,
    ) {
    }
}
