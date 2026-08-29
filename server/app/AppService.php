<?php
declare (strict_types = 1);

namespace app;

use app\Modules\Fixture\DeliveryRecord\ModuleProvider as DeliveryRecordModuleProvider;
use app\Modules\Official\Article\Application\ArticleAdministrationService;
use app\Modules\Official\Article\Contracts\ArticleAdministration;
use app\Modules\Official\File\Application\FileAdministrationService;
use app\Modules\Official\File\Contracts\FileAdministration;
use app\Modules\Official\ImportExport\Application\OperationLogExportApplicationService;
use app\Modules\Official\ImportExport\Application\TenantConfigurationTransferService;
use app\Modules\Official\Member\Application\MemberAdministrationService;
use app\Modules\Official\Member\Application\MemberBalanceContractService;
use app\Modules\Official\Member\Application\MemberProfileContractService;
use app\Modules\Official\Member\Application\MemberQueryService;
use app\Modules\Official\Member\Application\MemberTagContractService;
use app\Modules\Official\Member\Contracts\MemberAdministration;
use app\Modules\Official\Member\Contracts\MemberBalanceCommands;
use app\Modules\Official\Member\Contracts\MemberProfileCommands;
use app\Modules\Official\Member\Contracts\MemberQueries;
use app\Modules\Official\Member\Contracts\MemberTagCommands;
use app\Modules\Official\Notification\Application\NotificationApplicationService;
use app\Modules\Official\Notification\Contracts\NotificationCommands;
use app\Modules\Official\Notification\Contracts\NotificationQueries;
use app\Modules\Official\Oauth\Application\OAuthCommandService;
use app\Modules\Official\Oauth\Application\OAuthQueryService;
use app\Modules\Official\Oauth\Contracts\OAuthCommands;
use app\Modules\Official\Oauth\Contracts\OAuthQueries;
use app\api\application\OAuthApplicationService;
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
        $this->app->bind(ArticleAdministration::class, fn(): ArticleAdministration => new ArticleAdministrationService(
            $this->app->make(CurrentExecutionContext::class),
        ));
        $this->app->bind(FileAdministration::class, FileAdministrationService::class);
        $this->app->bind(OAuthCommands::class, fn(): OAuthCommands => new OAuthCommandService(
            $this->app->make(OAuthApplicationService::class),
        ));
        $this->app->bind(OAuthQueries::class, fn(): OAuthQueries => new OAuthQueryService());
        $this->app->bind(MemberQueries::class, fn(): MemberQueries => new MemberQueryService(
            $this->app->make(CurrentExecutionContext::class),
        ));
        $this->app->bind(MemberProfileCommands::class, fn(): MemberProfileCommands => new MemberProfileContractService());
        $this->app->bind(MemberTagCommands::class, fn(): MemberTagCommands => new MemberTagContractService());
        $this->app->bind(MemberBalanceCommands::class, fn(): MemberBalanceCommands => new MemberBalanceContractService());
        $this->app->bind(IdempotentCommandExecutor::class, fn(): IdempotentCommandExecutor => IdempotencyRuntimeFactory::forPdo(
            $this->database(),
        ));
        $this->app->bind(MemberAdministration::class, fn(): MemberAdministration => new MemberAdministrationService(
            $this->app->make(CurrentExecutionContext::class),
            $this->app->make(\app\common\service\XlsxExportService::class),
            $this->app->make(MemberQueries::class),
            $this->app->make(MemberProfileCommands::class),
            $this->app->make(MemberTagCommands::class),
            $this->app->make(MemberBalanceCommands::class),
            $this->app->make(IdempotentCommandExecutor::class),
        ));
        $this->app->bind(NotificationCommands::class, fn(): NotificationCommands => new NotificationApplicationService());
        $this->app->bind(NotificationQueries::class, fn(): NotificationQueries => new NotificationApplicationService());
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
        $this->app->bind(TenantConfigurationTransferService::class, fn(): TenantConfigurationTransferService => new TenantConfigurationTransferService(
            $this->database(),
        ));
        $this->app->bind(OperationLogExportApplicationService::class, fn(): OperationLogExportApplicationService => new OperationLogExportApplicationService(
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
