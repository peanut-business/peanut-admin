<?php
declare (strict_types = 1);

namespace app;

use app\Modules\Fixture\DeliveryRecord\ModuleProvider as DeliveryRecordModuleProvider;
use app\Modules\Official\Article\ModuleProvider as ArticleModuleProvider;
use app\Modules\Official\File\ModuleProvider as FileModuleProvider;
use app\Modules\Official\ImportExport\ModuleProvider as ImportExportModuleProvider;
use app\Modules\Official\Member\ModuleProvider as MemberModuleProvider;
use app\Modules\Official\Notification\ModuleProvider as NotificationModuleProvider;
use app\Modules\Official\Oauth\ModuleProvider as OauthModuleProvider;
use app\adminapi\service\generator\GeneratorImportPersistence;
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
use think\Service;
use think\facade\Config;
use think\facade\Db;
use PDO;

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
        $this->app->bind(AuditContractHost::class, fn(): AuditContractHost => AuditContractHost::fromPdo(
            $this->database(),
        ));
        $this->app->bind(InputValidator::class, fn(): InputValidator => new InputValidator(
            $this->app,
            $this->app->make(CurrentExecutionContext::class),
        ));
        $this->app->bind(GeneratorImportPersistence::class, fn(): GeneratorImportPersistence => new GeneratorImportPersistence(
            $this->database(),
        ));
        $this->app->bind(AdminAuthorizationQuery::class, fn(): AdminAuthorizationQuery => new AdminAuthorizationService(
            $this->database(),
        ));
        $this->app->bind(IdempotentCommandExecutor::class, fn(): IdempotentCommandExecutor => IdempotencyRuntimeFactory::forPdo(
            $this->database(),
        ));
        $this->app->bind(OutboundHttpTransport::class, fn(): OutboundHttpTransport => new GuzzleOutboundHttpTransport());
        $this->app->bind(ModuleExecutionBoundary::class, function (): ModuleExecutionBoundary {
            return new ModuleExecutionBoundary(
                $this->database(),
                $this->app->make(CurrentExecutionContext::class),
            );
        });
        $this->app->bind(AdminDirectoryQuery::class, fn(): AdminDirectoryQuery => new AdminDirectoryQuery(
            $this->database(),
            $this->app->make(CurrentExecutionContext::class),
        ));
        $this->app->bind(DepartmentAdministrationRuntime::class, fn(): DepartmentAdministrationRuntime => new DepartmentAdministrationRuntime(
            $this->database(),
            $this->app->make(CurrentExecutionContext::class),
        ));
        $this->app->bind(TenantAdminRuntime::class, fn(): TenantAdminRuntime => new TenantAdminRuntime(
            $this->database(),
        ));
        $this->app->bind(MenuPermissionUsageQuery::class, fn(): MenuPermissionUsageQuery => new MenuPermissionUsageQuery(
            $this->database(),
        ));
        $this->app->bind(RoleAdministrationRuntime::class, fn(): RoleAdministrationRuntime => new RoleAdministrationRuntime(
            $this->database(),
        ));
        $this->app->bind(TenantIdentityQuery::class, fn(): TenantIdentityQuery => new TenantIdentityQuery(
            $this->database(),
        ));
        $this->app->bind(PlatformOpsApplicationService::class, fn(): PlatformOpsApplicationService => new PlatformOpsApplicationService(
            $this->database(),
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

        foreach ([
            new ArticleModuleProvider(),
            new FileModuleProvider(),
            new ImportExportModuleProvider(),
            new MemberModuleProvider(),
            new NotificationModuleProvider(),
            new OauthModuleProvider(),
        ] as $provider) {
            $provider->register($this->app);
        }
        if (class_exists(DeliveryRecordModuleProvider::class)) {
            (new DeliveryRecordModuleProvider())->register($this->app);
        }
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
}
