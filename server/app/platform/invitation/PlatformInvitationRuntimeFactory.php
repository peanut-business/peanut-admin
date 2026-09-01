<?php
declare(strict_types=1);

namespace app\platform\invitation;

use app\Modules\Official\Notification\Contracts\NotificationCommands;
use app\common\contract\tenant\TenantSettingsBootstrapCommands;
use app\common\execution\ExecutionContextStore;
use app\platform\query\PlatformControlPlaneQueryService;
use app\platform\service\ApplicationTenantBootstrapService;
use app\platform\service\PlatformRuntimeFactory;
use app\platform\service\module\PdoModuleGovernanceProvider;
use PDO;
use think\facade\Config;

final class PlatformInvitationRuntimeFactory
{
    private ?TenantOwnerInvitationAdminService $invitations = null;
    private ?TenantOwnerInvitationPublicService $publicInvitations = null;
    private ?PlatformControlPlaneQueryService $queries = null;

    public function __construct(
        private readonly PDO $pdo,
        private readonly PlatformRuntimeFactory $platform,
        private readonly NotificationCommands $notifications,
        private readonly ExecutionContextStore $executionContexts,
        private readonly TenantSettingsBootstrapCommands $tenantSettings,
        private readonly PdoModuleGovernanceProvider $moduleGovernance,
    ) {
    }

    public function invitations(): TenantOwnerInvitationAdminService
    {
        return $this->invitations ??= new TenantOwnerInvitationAdminService(
            $this->pdo,
            $this->platform->sessions(),
            new UnavailableOwnerInvitationDeliveryPort(),
            OwnerInvitationRuntimePolicy::fromEnvironment(
                (string)env('APP_ENV', ''),
                (string)Config::get('platform_invitation.delivery_mode', 'auto')
            )
        );
    }

    public function publicInvitations(): TenantOwnerInvitationPublicService
    {
        return $this->publicInvitations ??= new TenantOwnerInvitationPublicService(
            $this->pdo,
            new ApplicationTenantBootstrapService(
                $this->pdo,
                $this->notifications,
                $this->executionContexts,
                $this->tenantSettings,
            ),
        );
    }

    public function queries(): PlatformControlPlaneQueryService
    {
        return $this->queries ??= new PlatformControlPlaneQueryService(
            $this->pdo,
            $this->platform->sessions(),
            $this->moduleGovernance->qualification(),
        );
    }
}
