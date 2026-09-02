<?php
declare(strict_types=1);

namespace app\platform\invitation;

use app\Modules\Official\Notification\Contracts\NotificationBootstrapCommands;
use app\Modules\Official\Task\Contracts\TaskBootstrapCommands;
use app\common\contract\tenant\TenantSettingsBootstrapCommands;
use app\common\execution\ExecutionContextStore;
use app\common\service\ApplicationPasswordPolicy;
use app\common\service\audit\AuditContractHost;
use app\platform\query\PlatformControlPlaneQueryService;
use app\platform\service\ApplicationTenantBootstrapService;
use app\platform\service\PlatformRuntimeFactory;
use app\platform\service\TenantApplicationBootstrapPersistence;
use app\platform\service\module\PdoModuleGovernanceProvider;
use PDO;
use PeanutAdmin\Kernel\Persistence\Pdo\PdoIdentityRepository;
use PeanutAdmin\Kernel\Persistence\Pdo\PdoMembershipRepository;
use PeanutAdmin\Kernel\Persistence\Pdo\PdoTenantRepository;
use PeanutAdmin\Kernel\Persistence\Pdo\PdoTransactionManager;
use think\facade\Config;

final class PlatformInvitationRuntimeFactory
{
    private ?TenantOwnerInvitationAdminService $invitations = null;
    private ?TenantOwnerInvitationPublicService $publicInvitations = null;
    private ?PlatformControlPlaneQueryService $queries = null;

    public function __construct(
        private readonly PDO $pdo,
        private readonly PlatformRuntimeFactory $platform,
        private readonly NotificationBootstrapCommands $notifications,
        private readonly TaskBootstrapCommands $tasks,
        private readonly ExecutionContextStore $executionContexts,
        private readonly TenantSettingsBootstrapCommands $tenantSettings,
        private readonly TenantApplicationBootstrapPersistence $bootstrapPersistence,
        private readonly PdoModuleGovernanceProvider $moduleGovernance,
        private readonly AuditContractHost $audit,
    ) {
    }

    public function invitations(): TenantOwnerInvitationAdminService
    {
        return $this->invitations ??= new TenantOwnerInvitationAdminService(
            $this->pdo,
            new PdoTransactionManager($this->pdo),
            new PdoTenantRepository($this->pdo),
            new PdoMembershipRepository($this->pdo),
            $this->platform->sessions(),
            new UnavailableOwnerInvitationDeliveryPort(),
            OwnerInvitationRuntimePolicy::fromEnvironment(
                (string)env('APP_ENV', ''),
                (string)Config::get('platform_invitation.delivery_mode', 'auto')
            ),
            $this->audit,
        );
    }

    public function publicInvitations(): TenantOwnerInvitationPublicService
    {
        return $this->publicInvitations ??= new TenantOwnerInvitationPublicService(
            $this->pdo,
            new PdoTransactionManager($this->pdo),
            new PdoIdentityRepository($this->pdo),
            new PdoMembershipRepository($this->pdo),
            new ApplicationTenantBootstrapService(
                $this->pdo,
                $this->notifications,
                $this->tasks,
                $this->executionContexts,
                $this->tenantSettings,
                $this->bootstrapPersistence,
            ),
            $this->audit,
            ApplicationPasswordPolicy::hasher(),
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
