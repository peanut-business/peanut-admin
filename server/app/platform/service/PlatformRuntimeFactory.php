<?php
declare(strict_types=1);

namespace app\platform\service;

use app\platform\identity\CorePlatformOperatorIdentityPort;
use app\platform\identity\PlatformOperatorAccountBoundary;
use app\platform\service\module\DeployedTenantModuleRegistry;
use app\platform\service\module\OpisManifestSchemaValidator;
use app\platform\service\module\OpisTenantModuleConfigValidator;
use app\platform\service\module\PlatformTenantModuleService;
use app\platform\service\module\ReflectionContractInspector;
use app\platform\service\module\StrictVersionConstraintMatcher;
use app\platform\service\module\VerifiedTenantModuleRepository;
use PDO;
use PeanutAdmin\DataPermission\Persistence\Schema\DataPermissionSchema;
use PeanutAdmin\Kernel\Auth\Persistence\PdoPlatformAuthRepository;
use PeanutAdmin\Kernel\Auth\PlatformAuthService;
use PeanutAdmin\Kernel\Auth\SystemClock;
use PeanutAdmin\Kernel\Auth\TokenIssuer;
use PeanutAdmin\Kernel\Authorization\RevisionPermissionCache;
use PeanutAdmin\Kernel\Identity\PasswordHasher;
use PeanutAdmin\Kernel\Idempotency\IdempotencySchema;
use PeanutAdmin\Kernel\Module\CompiledModuleRegistry;
use PeanutAdmin\Kernel\Module\ManifestDocument;
use PeanutAdmin\Kernel\Module\ModuleException;
use PeanutAdmin\Kernel\Module\ModuleBoundaryChecker;
use PeanutAdmin\Kernel\Module\ModuleHostLayout;
use PeanutAdmin\Kernel\Module\ModuleProvider;
use PeanutAdmin\Kernel\Module\ModuleRegistryCompiler;
use PeanutAdmin\Kernel\Module\Persistence\PdoModuleRuntimeRepository;
use PeanutAdmin\Kernel\Module\TenantModuleConfigValidator;
use PeanutAdmin\Kernel\Module\TenantModuleManager;
use PeanutAdmin\Kernel\Persistence\Pdo\PdoAuditRepository;
use PeanutAdmin\Kernel\Persistence\Pdo\PdoIdentityRepository;
use PeanutAdmin\Kernel\Persistence\Pdo\PdoMembershipRepository;
use PeanutAdmin\Kernel\Persistence\Pdo\PdoPlatformRepository;
use PeanutAdmin\Kernel\Persistence\Pdo\PdoTenantRepository;
use PeanutAdmin\Kernel\Persistence\Pdo\PdoTransactionManager;
use PeanutAdmin\Kernel\Persistence\Schema\KernelSchema;
use PeanutAdmin\Kernel\Authorization\Persistence\Schema\AuthorizationSchema;
use PeanutAdmin\Kernel\Migration\ModuleSchema;
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

    public static function platformAccess(): PlatformAccessAdminService
    {
        return self::$platformAccess ??= new PlatformAccessAdminService(self::pdo());
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
                new PasswordHasher()
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
        $roots = is_array($config) && is_array($config['roots'] ?? null)
            ? array_values($config['roots'])
            : [];
        if ($roots === [] || !array_is_list($roots)) {
            throw new ModuleException(
                'MODULE_REGISTRY_UNAVAILABLE',
                'No deployed Module roots are configured.'
            );
        }
        $serverRoot = dirname(__DIR__, 3);
        $resolvedRoots = [];
        foreach ($roots as $root) {
            if (!is_string($root) || trim($root) === '') {
                throw new ModuleException('MODULE_REGISTRY_UNAVAILABLE', 'A deployed Module root is invalid.');
            }
            $candidate = str_starts_with($root, DIRECTORY_SEPARATOR)
                ? $root
                : $serverRoot . '/' . ltrim($root, '/');
            $resolved = realpath($candidate);
            if ($resolved === false || !is_dir($resolved)) {
                throw new ModuleException('MODULE_REGISTRY_UNAVAILABLE', 'A deployed Module root is unavailable.');
            }
            $resolvedRoots[] = $resolved;
        }

        $kernelVersion = is_array($config) ? trim((string)($config['kernel_version'] ?? '')) : '';
        $frontend = is_array($config) && is_array($config['frontend_components'] ?? null)
            ? array_values($config['frontend_components'])
            : [];
        $clients = is_array($config) && is_array($config['registered_client_keys'] ?? null)
            ? array_values($config['registered_client_keys'])
            : [];
        if ($kernelVersion === '' || $clients === [] || !array_is_list($frontend) || !array_is_list($clients)) {
            throw new ModuleException('MODULE_REGISTRY_UNAVAILABLE', 'Module deployment metadata is invalid.');
        }

        $kernelRoot = dirname((new \ReflectionClass(ModuleProvider::class))->getFileName(), 3);
        $layout = new ModuleHostLayout('server/app/Modules', 'app\\Modules', 'web/src/modules');
        $compiler = new ModuleRegistryCompiler(
            new OpisManifestSchemaValidator($kernelRoot . '/resources/schemas/module-manifest.schema.json'),
            new StrictVersionConstraintMatcher(),
            new ReflectionContractInspector(),
            $kernelVersion,
            $frontend,
            $layout,
            [
                ...KernelSchema::tableNames(),
                ...AuthorizationSchema::tableNames(),
                ...ModuleSchema::tableNames(),
                ...IdempotencySchema::tableNames(),
                ...DataPermissionSchema::tableNames(),
            ],
            $clients
        );
        $registry = DeployedTenantModuleRegistry::compile($pdo, $resolvedRoots, $compiler);
        (new ModuleBoundaryChecker($registry->compiled(), $layout, ['pa_']))->check();
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
                new PasswordHasher()
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
