<?php
declare(strict_types=1);

namespace app\Modules\Official\Member;

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

    public function register(App $app): void
    {
        $app->bind(MemberQueries::class, fn(): MemberQueries => new MemberQueryService(
            $app->make(CurrentExecutionContext::class),
        ));
        $app->bind(MemberProfileCommands::class, fn(): MemberProfileCommands => new MemberProfileContractService());
        $app->bind(MemberTagCommands::class, fn(): MemberTagCommands => new MemberTagContractService());
        $app->bind(MemberBalanceCommands::class, fn(): MemberBalanceCommands => new MemberBalanceContractService());
        $app->bind(MemberAdministration::class, fn(): MemberAdministration => new MemberAdministrationService(
            $app->make(CurrentExecutionContext::class),
            $app->make(XlsxExportService::class),
            $app->make(MemberQueries::class),
            $app->make(MemberProfileCommands::class),
            $app->make(MemberTagCommands::class),
            $app->make(MemberBalanceCommands::class),
            $app->make(IdempotentCommandExecutor::class),
        ));
    }
}
