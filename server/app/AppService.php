<?php
declare (strict_types = 1);

namespace app;

use app\common\composition\ModuleComposition;
use app\common\contract\authorization\AdminAuthorizationQuery;
use app\common\contract\idempotency\IdempotentCommandExecutor;
use app\common\service\audit\AuditContractHost;
use app\common\execution\CurrentExecutionContext;
use app\common\execution\ExecutionContextStore;
use app\common\service\http\GuzzleOutboundHttpTransport;
use app\common\service\http\OutboundHttpTransport;
use app\common\service\authorization\AdminAuthorizationService;
use app\common\service\instance\DeploymentMode;
use app\common\service\idempotency\IdempotencyRuntimeFactory;
use app\common\service\module\ModuleExecutionBoundary;
use app\common\service\ApplicationPasswordPolicy;
use app\common\service\authorization\MenuPermissionUsageQuery;
use app\common\service\authorization\RoleAdministrationRuntime;
use app\common\service\org\AdminDirectoryQuery;
use app\common\service\org\DepartmentAdministrationRuntime;
use app\common\service\org\TenantAdminRuntime;
use app\common\service\tenant\TenantIdentityQuery;
use app\common\tenancy\DataScopePolicy;
use app\common\tenancy\MultiTenantDataScopePolicy;
use app\common\tenancy\StandaloneDataScopePolicy;
use app\common\validate\InputValidator;
use app\platform\service\ops\PlatformOpsApplicationService;
use app\platform\service\plugin\ModuleDefinitionRegistryFactory;
use app\platform\service\plugin\PluginLockResolver;
use think\Service;
use think\facade\Config;
use think\facade\Db;
use PDO;
use PeanutAdmin\Kernel\Auth\Persistence\PdoTenantAuthRepository;
use PeanutAdmin\Kernel\Auth\SystemClock;
use PeanutAdmin\Kernel\Auth\TenantAuthService;
use PeanutAdmin\Kernel\Auth\TokenIssuer;
use PeanutAdmin\Kernel\Http\TenantAuthEndpoint;
use PeanutAdmin\Kernel\Persistence\Pdo\PdoTransactionManager;
use PeanutAdmin\Kernel\Persistence\TransactionManager;

/**
 * 应用服务类
 */
class AppService extends Service
{
    public function register(): void
    {
        $contexts = new ExecutionContextStore();
        $this->app->instance(ExecutionContextStore::class, $contexts);
        $this->app->instance(CurrentExecutionContext::class, new CurrentExecutionContext($contexts));
        $this->app->bind(PDO::class, fn(): PDO => $this->database());
        $this->app->bind(TransactionManager::class, fn(): TransactionManager => new PdoTransactionManager(
            $this->app->make(PDO::class),
        ));
        $this->app->bind(TenantAuthService::class, function (): TenantAuthService {
            $key = trim((string)Config::get('tenant_auth.identifier_hmac_key', ''));
            if (strlen($key) < 32) {
                throw new \DomainException('TENANT_AUTH_CONFIGURATION_UNAVAILABLE');
            }
            $pdo = $this->app->make(PDO::class);
            return new TenantAuthService(
                $this->app->make(TransactionManager::class),
                new PdoTenantAuthRepository($pdo),
                ApplicationPasswordPolicy::hasher(),
                new SystemClock(),
                new TokenIssuer(),
                $key,
            );
        });
        $this->app->bind(TenantAuthEndpoint::class, fn(): TenantAuthEndpoint => new TenantAuthEndpoint(
            $this->app->make(TenantAuthService::class),
        ));
        $this->app->bind(AuditContractHost::class, fn(): AuditContractHost => AuditContractHost::fromPdo(
            $this->app->make(PDO::class),
        ));
        $this->app->bind(InputValidator::class, fn(): InputValidator => new InputValidator(
            $this->app,
            $this->app->make(CurrentExecutionContext::class),
        ));
        $this->app->bind(AdminAuthorizationQuery::class, fn(): AdminAuthorizationQuery => new AdminAuthorizationService(
            $this->app->make(PDO::class),
        ));
        $this->app->bind(IdempotentCommandExecutor::class, fn(): IdempotentCommandExecutor => IdempotencyRuntimeFactory::forPdo(
            $this->app->make(PDO::class),
        ));
        $this->app->bind(OutboundHttpTransport::class, fn(): OutboundHttpTransport => new GuzzleOutboundHttpTransport());
        $this->app->bind(ModuleExecutionBoundary::class, function (): ModuleExecutionBoundary {
            return new ModuleExecutionBoundary(
                $this->app->make(PDO::class),
                $this->app->make(CurrentExecutionContext::class),
            );
        });
        $this->app->bind(AdminDirectoryQuery::class, fn(): AdminDirectoryQuery => new AdminDirectoryQuery(
            $this->app->make(PDO::class),
            $this->app->make(CurrentExecutionContext::class),
        ));
        $this->app->bind(DepartmentAdministrationRuntime::class, fn(): DepartmentAdministrationRuntime => new DepartmentAdministrationRuntime(
            $this->app->make(PDO::class),
            $this->app->make(CurrentExecutionContext::class),
        ));
        $this->app->bind(TenantAdminRuntime::class, fn(): TenantAdminRuntime => new TenantAdminRuntime(
            $this->app->make(PDO::class),
        ));
        $this->app->bind(MenuPermissionUsageQuery::class, fn(): MenuPermissionUsageQuery => new MenuPermissionUsageQuery(
            $this->app->make(PDO::class),
        ));
        $this->app->bind(RoleAdministrationRuntime::class, fn(): RoleAdministrationRuntime => new RoleAdministrationRuntime(
            $this->app->make(PDO::class),
        ));
        $this->app->bind(TenantIdentityQuery::class, fn(): TenantIdentityQuery => new TenantIdentityQuery(
            $this->app->make(PDO::class),
        ));
        $this->app->bind(PlatformOpsApplicationService::class, fn(): PlatformOpsApplicationService => new PlatformOpsApplicationService(
            $this->app->make(PDO::class),
        ));
        $this->app->bind(DataScopePolicy::class, function (): DataScopePolicy {
            $mode = DeploymentMode::fromConfiguredValue(Config::get('deployment.mode'));
            return match ($mode) {
                DeploymentMode::MultiTenant => new MultiTenantDataScopePolicy(
                    $this->app->make(CurrentExecutionContext::class),
                ),
                DeploymentMode::Standalone => new StandaloneDataScopePolicy(),
                default => throw new \RuntimeException('DEPLOYMENT_MODE_UNCONFIGURED'),
            };
        });

        $this->registerModules();
    }

    public function boot(): void
    {
    }

    private function database(): PDO
    {
        $pdo = Db::connect()->connect();
        if (!$pdo instanceof PDO) {
            throw new \RuntimeException('APPLICATION_DATABASE_UNAVAILABLE');
        }
        return $pdo;
    }

    private function registerModules(): void
    {
        $config = Config::get('modules', []);
        if (!is_array($config)) {
            throw new \RuntimeException('MODULE_REGISTRY_UNAVAILABLE');
        }
        $serverRoot = dirname(__DIR__);
        $lockPath = trim((string)($config['plugin_lock'] ?? ''));
        if ($lockPath === '') {
            throw new \RuntimeException('PLUGIN_LOCK_INVALID');
        }
        $registry = (new ModuleDefinitionRegistryFactory($serverRoot))->fromPluginLock(
            new PluginLockResolver($serverRoot, $lockPath),
            $config,
            false,
        );
        (new ModuleComposition($this->app))->register($registry);
    }
}
