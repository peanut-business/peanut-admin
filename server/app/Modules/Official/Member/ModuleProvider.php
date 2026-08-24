<?php
declare(strict_types=1);

namespace app\Modules\Official\Member;

use app\Modules\Official\Member\Application\MemberBalanceContractService;
use app\Modules\Official\Member\Application\MemberQueryService;
use app\Modules\Official\Member\Contracts\MemberBalanceCommands;
use app\Modules\Official\Member\Contracts\MemberQueries;
use PeanutAdmin\Kernel\Module\ModuleProvider as ModuleProviderContract;

final class ModuleProvider implements ModuleProviderContract
{
    public function moduleKey(): string
    {
        return 'official.member';
    }

    public function queries(): MemberQueries
    {
        return new MemberQueryService();
    }

    public function balanceCommands(): MemberBalanceCommands
    {
        return new MemberBalanceContractService();
    }
}
