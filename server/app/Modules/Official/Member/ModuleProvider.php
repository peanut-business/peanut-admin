<?php
declare(strict_types=1);

namespace app\Modules\Official\Member;

use app\Modules\Official\Member\Application\MemberAdministrationService;
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
use app\Modules\Official\Member\Contracts\MemberSubjectLookup;
use app\Modules\Official\Member\Infrastructure\Persistence\ThinkPhpMemberSubjectLookup;
use PeanutAdmin\Kernel\Module\ModuleProvider as ModuleProviderContract;

final class ModuleProvider implements ModuleProviderContract
{
    public function moduleKey(): string
    {
        return 'official.member';
    }

    public function bindings(): array
    {
        return [
            MemberQueries::class => MemberQueryService::class,
            MemberSubjectLookup::class => ThinkPhpMemberSubjectLookup::class,
            MemberIdentityCommands::class => MemberIdentityContractService::class,
            MemberProfileCommands::class => MemberProfileContractService::class,
            MemberTagCommands::class => MemberTagContractService::class,
            MemberBalanceCommands::class => MemberBalanceContractService::class,
            MemberAdministration::class => MemberAdministrationService::class,
        ];
    }
}
