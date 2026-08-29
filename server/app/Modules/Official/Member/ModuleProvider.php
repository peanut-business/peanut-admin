<?php
declare(strict_types=1);

namespace app\Modules\Official\Member;

use app\Modules\Official\Member\Application\MemberBalanceContractService;
use app\Modules\Official\Member\Application\MemberIdentityContractService;
use app\Modules\Official\Member\Application\MemberQueryService;
use app\Modules\Official\Member\Application\MemberProfileContractService;
use app\Modules\Official\Member\Application\MemberTagContractService;
use app\Modules\Official\Member\Contracts\MemberBalanceCommands;
use app\Modules\Official\Member\Contracts\MemberAdministration;
use app\Modules\Official\Member\Contracts\MemberIdentityCommands;
use app\Modules\Official\Member\Contracts\MemberProfileCommands;
use app\Modules\Official\Member\Contracts\MemberQueries;
use app\Modules\Official\Member\Contracts\MemberTagCommands;
use PeanutAdmin\Kernel\Module\ModuleProvider as ModuleProviderContract;

final class ModuleProvider implements ModuleProviderContract
{
    public function moduleKey(): string
    {
        return 'official.member';
    }

    public function administration(): MemberAdministration
    {
        return app(MemberAdministration::class);
    }

    public function queries(): MemberQueries
    {
        return app(MemberQueries::class);
    }

    public function balanceCommands(): MemberBalanceCommands
    {
        return new MemberBalanceContractService();
    }

    public function profileCommands(): MemberProfileCommands
    {
        return new MemberProfileContractService();
    }

    public function identityCommands(): MemberIdentityCommands
    {
        return new MemberIdentityContractService();
    }

    public function tagCommands(): MemberTagCommands
    {
        return new MemberTagContractService();
    }
}
