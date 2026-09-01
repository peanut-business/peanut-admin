<?php
declare(strict_types=1);

namespace app\platform\service;

use app\Modules\Official\Notification\Contracts\NotificationBootstrapCommands;
use app\Modules\Official\Task\Contracts\TaskBootstrapCommands;
use app\common\contract\tenant\TenantSettingsBootstrapCommands;
use app\common\execution\ExecutionContextStore;
use app\common\service\ApplicationPasswordPolicy;
use app\common\service\audit\AuditContractHost;
use app\platform\identity\CorePlatformOperatorIdentityPort;
use app\platform\service\module\OpisTenantModuleConfigValidator;
use app\platform\service\module\PdoModuleGovernanceProvider;
use app\platform\service\module\PlatformTenantModuleService;
use app\platform\service\module\VerifiedTenantModuleRepository;
use app\platform\service\plugin\PlatformModuleRuntimeService;
use PDO;
use PeanutAdmin\Kernel\Auth\Persistence\PdoPlatformAuthRepository;
use PeanutAdmin\Kernel\Auth\PlatformAuthService;
use PeanutAdmin\Kernel\Auth\SystemClock;
use PeanutAdmin\Kernel\Auth\TokenIssuer;
use PeanutAdmin\Kernel\Authorization\RevisionPermissionCache;
use PeanutAdmin\Kernel\Module\CompiledModuleRegistry;
use PeanutAdmin\Kernel\Module\ManifestDocument;
use PeanutAdmin\Kernel\Module\Persistence\PdoModuleRuntimeRepository;
use PeanutAdmin\Kernel\Module\TenantModuleConfigValidator;
use PeanutAdmin\Kernel\Module\TenantModuleManager;
use PeanutAdmin\Kernel\Persistence\Pdo\PdoIdentityRepository;
use PeanutAdmin\Kernel\Persistence\Pdo\PdoMembershipRepository;
use PeanutAdmin\Kernel\Persistence\Pdo\PdoPlatformRepository;
use PeanutAdmin\Kernel\Persistence\Pdo\PdoTenantRepository;
use PeanutAdmin\Kernel\Persistence\Pdo\PdoTransactionManager;
use PeanutAdmin\Kernel\Platform\Authorization\PdoPlatformAuthorizationRepository;
use PeanutAdmin\Kernel\Platform\Authorization\PlatformAuthorizationEvaluator;
use PeanutAdmin\Kernel\Platform\Application\PlatformTenantAdminService;
use PeanutAdmin\Kernel\Platform\Application\PlatformAccessAdminService;
use PeanutAdmin\Kernel\Platform\Bootstrap\BootstrapService;
use PeanutAdmin\Kernel\Platform\Application\PlatformWorkspaceQueryService;

final class PlatformRuntimeFactory
{
    private ?PlatformOperatorSessionService $sessions = null;
    private ?PlatformTenantQueryService $tenantQueries = null;
    private ?TenantEntryBindingAdminService $tenantEntryBindings = null;
    private ?TenantGovernanceService $tenantGovernance = null;
    private ?PlatformTenantModuleService $tenantModules = null;
    private ?PlatformAccessAdminService $platformAccess = null;
    private ?PlatformModuleRuntimeService $moduleRuntime = null;

    /** @param array<string,mixed> $moduleConfig @param array<string,mixed> $trustedModuleKeyConfig */
    public function __construct(
        private readonly PDO $pdo,
        private readonly NotificationBootstrapCommands $notifications,
        private readonly TaskBootstrapCommands $tasks,
        private readonly ExecutionContextStore $executionContexts,
        private readonly TenantSettingsBootstrapCommands $tenantSettings,
        private readonly TenantApplicationBootstrapPersistence $bootstrapPersistence,
        private readonly string $identifierHmacKey,
        private readonly array $moduleConfig,
        private readonly array $trustedModuleKeyConfig,
    ) {
    }

    public function sessions(): PlatformOperatorSessionService
    {
        if ($this->sessions !== null) {
            return $this->sessions;
        }

        $key = trim($this->identifierHmacKey);
        if (strlen($key) < 32) {
            throw new \DomainException('PLATFORM_AUTH_CONFIGURATION_UNAVAILABLE');
        }
        $pdo = $this->pdo;
        $auth = new PlatformAuthService(
            new PdoTransactionManager($pdo),
            new PdoPlatformAuthRepository($pdo),
            ApplicationPasswordPolicy::hasher(),
            new SystemClock(),
            new TokenIssuer(),
            $key
        );
        $permissions = new PdoPlatformAuthorizationRepository($pdo);

        return $this->sessions = new PlatformOperatorSessionService(
            $auth,
            new PlatformAuthorizationEvaluator($permissions, new RevisionPermissionCache()),
            $permissions
        );
    }

    public function identities(): CorePlatformOperatorIdentityPort
    {
        return new CorePlatformOperatorIdentityPort($this->sessions());
    }

    public function tenantQueries(): PlatformTenantQueryService
    {
        if ($this->tenantQueries !== null) {
            return $this->tenantQueries;
        }

        return $this->tenantQueries = new PlatformTenantQueryService(
            $this->sessions(),
            new PlatformWorkspaceQueryService($this->pdo)
        );
    }

    public function tenantEntryBindings(): TenantEntryBindingAdminService
    {
        return $this->tenantEntryBindings ??= new TenantEntryBindingAdminService(
            $this->pdo,
            $this->sessions(),
            AuditContractHost::fromPdo($this->pdo),
        );
    }

    public function platformAccess(): PlatformAccessAdminService
    {
        return $this->platformAccess ??= new PlatformAccessAdminService($this->pdo, ApplicationPasswordPolicy::hasher());
    }

    public function tenantGovernance(): TenantGovernanceService
    {
        if ($this->tenantGovernance !== null) {
            return $this->tenantGovernance;
        }

        $pdo = $this->pdo;
        $transactions = new PdoTransactionManager($pdo);
        $audit = AuditContractHost::fromPdo($pdo);
        $modules = new TenantModuleManager(
            new CompiledModuleRegistry([], [], [], [], 'platform-lifecycle-only'),
            new PdoModuleRuntimeRepository($pdo),
            new class implements TenantModuleConfigValidator {
                public function assertValid(ManifestDocument $manifest, array $config): void
                {
                    throw new \DomainException('PLATFORM_MODULE_REGISTRY_UNAVAILABLE');
                }
            }
        );

        return $this->tenantGovernance = new TenantGovernanceService(
            $this->identities(),
            $transactions,
            new BootstrapService(
                $transactions,
                new PdoIdentityRepository($pdo),
                new PdoTenantRepository($pdo),
                new PdoMembershipRepository($pdo),
                new PdoPlatformRepository($pdo),
                $audit,
                ApplicationPasswordPolicy::hasher()
            ),
            new PlatformTenantAdminService($pdo, $modules),
            $this->ownerAdminProvisioner()
        );
    }

    public function tenantModules(): PlatformTenantModuleService
    {
        if ($this->tenantModules !== null) {
            return $this->tenantModules;
        }

        $pdo = $this->pdo;
        $governance = new PdoModuleGovernanceProvider($pdo, dirname(__DIR__, 3), $this->moduleConfig);
        $registry = $governance->registry();
        $validator = new OpisTenantModuleConfigValidator();
        $repository = new VerifiedTenantModuleRepository(
            new PdoModuleRuntimeRepository($pdo, true),
            $registry
        );
        $manager = new TenantModuleManager($registry->compiled(), $repository, $validator);
        $transactions = new PdoTransactionManager($pdo);
        $audit = AuditContractHost::fromPdo($pdo);
        $governance = new TenantGovernanceService(
            $this->identities(),
            $transactions,
            new BootstrapService(
                $transactions,
                new PdoIdentityRepository($pdo),
                new PdoTenantRepository($pdo),
                new PdoMembershipRepository($pdo),
                new PdoPlatformRepository($pdo),
                $audit,
                ApplicationPasswordPolicy::hasher()
            ),
            new PlatformTenantAdminService($pdo, $manager),
            $this->ownerAdminProvisioner()
        );

        return $this->tenantModules = new PlatformTenantModuleService(
            $this->sessions(),
            $governance,
            $registry,
            $validator
        );
    }

    public function moduleRuntime(): PlatformModuleRuntimeService
    {
        if ($this->moduleRuntime !== null) return $this->moduleRuntime;
        $trusted = [];
        foreach ($this->trustedModuleKeyConfig as $keyId => $encoded) {
            $decoded = is_string($encoded) ? base64_decode($encoded, true) : false;
            if (is_string($keyId) && is_string($decoded) && strlen($decoded) === SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES) $trusted[$keyId] = $decoded;
        }
        return $this->moduleRuntime = new PlatformModuleRuntimeService(
            $this->pdo,
            dirname(__DIR__, 3),
            $this->moduleConfig,
            $trusted,
        );
    }

    private function ownerAdminProvisioner(): PdoTenantOwnerAdminProvisioner
    {
        return new PdoTenantOwnerAdminProvisioner(
            $this->pdo,
            new ApplicationTenantBootstrapService(
                $this->pdo,
                $this->notifications,
                $this->tasks,
                $this->executionContexts,
                $this->tenantSettings,
                $this->bootstrapPersistence,
            ),
        );
    }
}
