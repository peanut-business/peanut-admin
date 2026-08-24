<?php
declare(strict_types=1);

namespace app\platform\service;

use app\common\service\ApplicationPasswordPolicy;
use app\platform\identity\CorePlatformOperatorIdentityPort;
use app\platform\service\module\OpisTenantModuleConfigValidator;
use app\platform\service\module\PdoModuleGovernanceProvider;
use app\platform\service\module\PlatformTenantModuleService;
use app\platform\service\module\VerifiedTenantModuleRepository;
use PDO;
use PeanutAdmin\Kernel\Auth\Persistence\PdoPlatformAuthRepository;
use PeanutAdmin\Kernel\Auth\PlatformAuthService;
use PeanutAdmin\Kernel\Auth\SystemClock;
use PeanutAdmin\Kernel\Auth\TokenIssuer;
use PeanutAdmin\Kernel\Authorization\RevisionPermissionCache;
use PeanutAdmin\Kernel\Module\CompiledModuleRegistry;
use PeanutAdmin\Kernel\Module\ManifestDocument;
use PeanutAdmin\Kernel\Module\ModuleException;
use PeanutAdmin\Kernel\Module\Persistence\PdoModuleRuntimeRepository;
use PeanutAdmin\Kernel\Module\TenantModuleConfigValidator;
use PeanutAdmin\Kernel\Module\TenantModuleManager;
use PeanutAdmin\Kernel\Persistence\Pdo\PdoAuditRepository;
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
use think\facade\Config;
use think\facade\Db;

final class PlatformRuntimeFactory
{
    private static ?PlatformOperatorSessionService $sessions = null;
    private static ?PlatformTenantQueryService $tenantQueries = null;
    private static ?TenantEntryBindingAdminService $tenantEntryBindings = null;
    private static ?TenantGovernanceService $tenantGovernance = null;
    private static ?PlatformTenantModuleService $tenantModules = null;
    private static ?PlatformAccessAdminService $platformAccess = null;

    public static function sessions(): PlatformOperatorSessionService
    {
        if (self::$sessions !== null) {
            return self::$sessions;
        }

        $key = trim((string)Config::get('platform_auth.identifier_hmac_key', ''));
        if (strlen($key) < 32) {
            throw new \DomainException('PLATFORM_AUTH_CONFIGURATION_UNAVAILABLE');
        }
        $pdo = Db::connect()->connect();
        if (!$pdo instanceof PDO) {
            throw new \RuntimeException('PLATFORM_DATABASE_CONNECTION_UNAVAILABLE');
        }
        $auth = new PlatformAuthService(
            new PdoTransactionManager($pdo),
            new PdoPlatformAuthRepository($pdo),
            ApplicationPasswordPolicy::hasher(),
            new SystemClock(),
            new TokenIssuer(),
            $key
        );
        $permissions = new PdoPlatformAuthorizationRepository($pdo);

        return self::$sessions = new PlatformOperatorSessionService(
            $auth,
            new PlatformAuthorizationEvaluator($permissions, new RevisionPermissionCache()),
            $permissions
        );
    }

    public static function identities(): CorePlatformOperatorIdentityPort
    {
        return new CorePlatformOperatorIdentityPort(self::sessions());
    }

    public static function tenantQueries(): PlatformTenantQueryService
    {
        if (self::$tenantQueries !== null) {
            return self::$tenantQueries;
        }

        return self::$tenantQueries = new PlatformTenantQueryService(
            self::sessions(),
            new PlatformWorkspaceQueryService(self::pdo())
        );
    }

    public static function tenantEntryBindings(): TenantEntryBindingAdminService
    {
        return self::$tenantEntryBindings ??= new TenantEntryBindingAdminService(
            self::pdo(),
            self::sessions()
        );
    }

    public static function platformAccess(): PlatformAccessAdminService
    {
        return self::$platformAccess ??= new PlatformAccessAdminService(self::pdo(), ApplicationPasswordPolicy::hasher());
    }

    public static function tenantGovernance(): TenantGovernanceService
    {
        if (self::$tenantGovernance !== null) {
            return self::$tenantGovernance;
        }

        $pdo = self::pdo();
        $transactions = new PdoTransactionManager($pdo);
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

        return self::$tenantGovernance = new TenantGovernanceService(
            self::identities(),
            $transactions,
            new BootstrapService(
                $transactions,
                new PdoIdentityRepository($pdo),
                new PdoTenantRepository($pdo),
                new PdoMembershipRepository($pdo),
                new PdoPlatformRepository($pdo),
                new PdoAuditRepository($pdo),
                ApplicationPasswordPolicy::hasher()
            ),
            new PlatformTenantAdminService($pdo, $modules),
            new PdoTenantOwnerAdminProvisioner($pdo)
        );
    }

    public static function tenantModules(): PlatformTenantModuleService
    {
        if (self::$tenantModules !== null) {
            return self::$tenantModules;
        }

        $pdo = self::pdo();
        $config = Config::get('modules', []);
        if (!is_array($config)) {
            throw new ModuleException('MODULE_REGISTRY_UNAVAILABLE', 'Module deployment metadata is invalid.');
        }
        $governance = new PdoModuleGovernanceProvider($pdo, dirname(__DIR__, 3), $config);
        $registry = $governance->registry();
        $validator = new OpisTenantModuleConfigValidator();
        $repository = new VerifiedTenantModuleRepository(
            new PdoModuleRuntimeRepository($pdo, true),
            $registry
        );
        $manager = new TenantModuleManager($registry->compiled(), $repository, $validator);
        $transactions = new PdoTransactionManager($pdo);
        $governance = new TenantGovernanceService(
            self::identities(),
            $transactions,
            new BootstrapService(
                $transactions,
                new PdoIdentityRepository($pdo),
                new PdoTenantRepository($pdo),
                new PdoMembershipRepository($pdo),
                new PdoPlatformRepository($pdo),
                new PdoAuditRepository($pdo),
                ApplicationPasswordPolicy::hasher()
            ),
            new PlatformTenantAdminService($pdo, $manager),
            new PdoTenantOwnerAdminProvisioner($pdo)
        );

        return self::$tenantModules = new PlatformTenantModuleService(
            self::sessions(),
            $governance,
            $registry,
            $validator
        );
    }

    private static function pdo(): PDO
    {
        $pdo = Db::connect()->connect();
        if (!$pdo instanceof PDO) {
            throw new \RuntimeException('PLATFORM_DATABASE_CONNECTION_UNAVAILABLE');
        }
        return $pdo;
    }

    private function __construct()
    {
    }
}
