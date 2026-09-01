<?php
declare(strict_types=1);

namespace app\Modules\Official\Member;

use app\common\composition\ModuleBindingContributor;
use app\Modules\Official\Member\Application\MemberBalanceContractService;
use app\Modules\Official\Member\Application\MemberIdentityContractService;
use app\Modules\Official\Member\Application\MemberQueryService;
use app\Modules\Official\Member\Application\MemberProfileContractService;
use app\Modules\Official\Member\Application\MemberTagContractService;
use app\common\contract\idempotency\IdempotentCommandExecutor;
use app\common\execution\CurrentExecutionContext;
use app\common\service\XlsxExportService;
use app\Modules\Official\Member\Contracts\MemberBalanceCommands;
use app\Modules\Official\Member\Contracts\MemberAdministration;
use app\Modules\Official\Member\Contracts\MemberIdentityCommands;
use app\Modules\Official\Member\Contracts\MemberProfileCommands;
use app\Modules\Official\Member\Contracts\MemberQueries;
use app\Modules\Official\Member\Contracts\MemberTagCommands;
use PeanutAdmin\Kernel\Module\ModuleProvider as ModuleProviderContract;
use think\App;

final class ModuleProvider implements ModuleProviderContract, ModuleBindingContributor
{
    public function moduleKey(): string
    {
        return 'official.member';
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

    public function bindings(): array
    {
        return [
            MemberQueries::class => fn(App $app): MemberQueries => new MemberQueryService(
                $app->make(CurrentExecutionContext::class),
            ),
            MemberIdentityCommands::class => fn(): MemberIdentityCommands => $this->identityCommands(),
            MemberProfileCommands::class => fn(): MemberProfileCommands => $this->profileCommands(),
            MemberTagCommands::class => fn(): MemberTagCommands => $this->tagCommands(),
            MemberBalanceCommands::class => fn(): MemberBalanceCommands => $this->balanceCommands(),
            MemberAdministration::class => fn(App $app): MemberAdministration => new MemberAdministrationService(
                $app->make(CurrentExecutionContext::class),
                $app->make(XlsxExportService::class),
                $app->make(MemberQueries::class),
                $app->make(MemberProfileCommands::class),
                $app->make(MemberTagCommands::class),
                $app->make(MemberBalanceCommands::class),
                $app->make(IdempotentCommandExecutor::class),
                $app->make(\PeanutAdmin\Kernel\Persistence\TransactionManager::class),
            ),
        ];
    }
}
