<?php
declare(strict_types=1);

namespace app\Modules\Official\Member\Contracts\Dto;

final readonly class MemberBalanceLogPage
{
    /** @param list<array<string,mixed>> $items */
    public function __construct(
        public array $items,
        public int $total,
        public int $page,
        public int $pageSize,
    ) {
    }
}
