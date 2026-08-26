<?php
declare(strict_types=1);

namespace app\Modules\Official\Member\Contracts;

use app\Modules\Official\Member\Application\MemberBalanceContractService;

/** Public Member contract resolver for dependent Modules. */
final class MemberContracts
{
    public static function balanceCommands(): MemberBalanceCommands
    {
        return new MemberBalanceContractService();
    }
}
