<?php
declare(strict_types=1);

namespace app\platform\service;

use app\platform\identity\CorePlatformOperatorIdentityPort;
use app\platform\identity\PlatformOperatorAccountBoundary;
use PDO;
use PeanutAdmin\Kernel\Auth\Persistence\PdoPlatformAuthRepository;
use PeanutAdmin\Kernel\Auth\PlatformAuthService;
use PeanutAdmin\Kernel\Auth\SystemClock;
use PeanutAdmin\Kernel\Auth\TokenIssuer;
use PeanutAdmin\Kernel\Authorization\RevisionPermissionCache;
use PeanutAdmin\Kernel\Identity\PasswordHasher;
use PeanutAdmin\Kernel\Module\CompiledModuleRegistry;
use PeanutAdmin\Kernel\Module\ManifestDocument;
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
use PeanutAdmin\Kernel\Platform\Bootstrap\BootstrapService;
use PeanutAdmin\Kernel\Platform\Application\PlatformWorkspaceQueryService;
use think\facade\Config;
use think\facade\Db;

final class PlatformRuntimeFactory
{
    private static ?PlatformOperatorSessionService $sessions = null;
    private static ?PlatformTenantQueryService $tenantQueries = null;
    private static ?TenantGovernanceService $tenantGovernance = null;

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
            new PasswordHasher(),
            new SystemClock(),
            new TokenIssuer(),
            $key
        );
        $permissions = new PdoPlatformAuthorizationRepository($pdo);

        return self::$sessions = new PlatformOperatorSessionService(
            $auth,
            new PlatformAuthorizationEvaluator($permissions, new RevisionPermissionCache()),
            $permissions,
            new PlatformOperatorAccountBoundary($pdo)
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
            new BootstrapService(
                $transactions,
                new PdoIdentityRepository($pdo),
                new PdoTenantRepository($pdo),
                new PdoMembershipRepository($pdo),
                new PdoPlatformRepository($pdo),
                new PdoAuditRepository($pdo),
                new PasswordHasher()
            ),
            new PlatformTenantAdminService($pdo, $modules)
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
