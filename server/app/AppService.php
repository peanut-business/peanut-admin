<?php
declare (strict_types = 1);

namespace app;

use app\adminapi\application\config\ConfigApplicationService;
use app\adminapi\application\generator\GeneratorApplicationService;
use app\adminapi\application\WorkbenchApplicationService;
use app\adminapi\infrastructure\generator\ThinkPhpGeneratorMetadata;
use app\adminapi\service\AdminApiAccessRegistry;
use app\adminapi\service\AdminLoginAttemptService;
use app\adminapi\service\OperationLogService;
use app\adminapi\service\generator\GeneratorImportPersistence;
use app\api\application\IndexApplicationService;
use app\api\application\LoginApplicationService as MemberLoginApplicationService;
use app\api\service\UserTokenService;
use app\Modules\Official\Article\Contracts\PublicArticleQueries;
use app\common\composition\ModuleComposition;
use app\common\contract\AdminPermissionPolicy;
use app\common\contract\authorization\AdminAuthorizationQuery;
use app\common\contract\authorization\AdminMenuPersistence;
use app\common\contract\idempotency\IdempotentCommandExecutor;
use app\common\contract\tenant\TenantSettingsBootstrapCommands;
use app\common\service\audit\AuditContractHost;
use app\common\service\external\ExternalTenantAudit;
use app\common\service\external\ThinkPhpExternalTenantAudit;
use app\common\execution\CurrentExecutionContext;
use app\common\execution\ExecutionContextStore;
use app\common\model\TenantOwnedModel;
use app\common\service\http\GuzzleOutboundHttpTransport;
use app\common\service\http\OutboundHttpTransport;
use app\common\service\authorization\AdminAuthorizationService;
use app\common\service\authorization\CoreTenantModuleAdminBridge;
use app\common\service\instance\DeploymentMode;
use app\common\service\idempotency\IdempotencyRuntimeFactory;
use app\common\service\installation\InstallationExecutionHost;
use app\common\service\module\ModuleExecutionBoundary;
use app\common\service\ApplicationPasswordPolicy;
use app\common\service\CoreServiceOverrides;
use app\common\service\CrontabCommandService;
use app\common\service\DemoAccountPolicy;
use app\common\service\FileService;
use app\common\service\ProductAssetReferenceService;
use app\common\service\authorization\MenuPermissionUsageQuery;
use app\common\service\authorization\NativeAdminPrincipalRepository;
use app\common\service\authorization\RoleAdministrationRuntime;
use app\common\service\authorization\ThinkPhpAdminMenuPersistence;
use app\common\service\org\AdminDirectoryQuery;
use app\common\service\org\DepartmentAdministrationRuntime;
use app\common\service\org\TenantAdminRuntime;
use app\common\service\tenant\TenantIdentityQuery;
use app\common\service\storage\AliyunStorageClientFactory;
use app\common\service\storage\FailClosedStorageCredentialResolver;
use app\common\service\storage\QcloudStorageClientFactory;
use app\common\service\storage\StorageCredentialResolver;
use app\common\service\storage\StorageConfigurationService;
use app\common\service\storage\StorageDriverFactory;
use app\common\service\storage\StorageRepository;
use app\common\service\storage\StorageService;
use app\common\service\tenant\DefaultTenantContextResolver;
use app\common\service\tenant\TenantSettingsBootstrapRuntimeFactory;
use app\common\service\tenant\TenantEntryBindingResolver;
use app\common\service\tenant\ApplicationHostPolicy;
use app\common\tenancy\DataScopePolicy;
use app\common\tenancy\MultiTenantDataScopePolicy;
use app\common\tenancy\StandaloneDataScopePolicy;
use app\common\validate\InputValidator;
use app\platform\service\PlatformRuntimeFactory;
use app\platform\service\TenantApplicationBootstrapPersistence;
use app\platform\infrastructure\ThinkPhpTenantApplicationBootstrapPersistence;
use app\platform\service\ops\PlatformOpsApplicationService;
use app\platform\service\ops\ApplicationRuntimeStatusProvider;
use app\platform\service\ops\PlatformOpsRuntimeFactory;
use app\platform\service\module\PdoModuleGovernanceProvider;
use app\platform\service\plugin\ModuleDefinitionRegistryFactory;
use app\platform\service\plugin\PluginLockResolver;
use think\Service;
use think\Model;
use think\facade\Config;
use think\facade\Db;
use PDO;
use PeanutAdmin\Kernel\Auth\Persistence\PdoTenantAuthRepository;
use PeanutAdmin\Kernel\Auth\SystemClock;
use PeanutAdmin\Kernel\Auth\TenantAuthService;
use PeanutAdmin\Kernel\Auth\TokenIssuer;
use PeanutAdmin\Kernel\Authorization\Application\RoleAdminService;
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
        $this->registerExecutionContext();
        $this->registerAuthentication();
        $this->registerAuthorization();
        $this->registerStorage();
        $this->registerPlatform();
        $this->registerApplicationServices();
        $this->registerModules();
    }

    private function registerExecutionContext(): void
    {
        $contexts = new ExecutionContextStore();
        $current = new CurrentExecutionContext($contexts);
        $this->app->instance(ExecutionContextStore::class, $contexts);
        $this->app->instance(CurrentExecutionContext::class, $current);
        $configuredOverrides = Config::get('peanut.overrides', []);
        CoreServiceOverrides::configure(is_array($configuredOverrides) ? $configuredOverrides : []);
        $this->app->bind(PDO::class, fn(): PDO => $this->database());
        $this->app->bind(TransactionManager::class, fn(): TransactionManager => new PdoTransactionManager(
            $this->app->make(PDO::class),
        ));
        $this->app->bind(IdempotentCommandExecutor::class, fn(): IdempotentCommandExecutor => IdempotencyRuntimeFactory::forPdo(
            $this->app->make(PDO::class),
        ));
        $this->app->bind(OutboundHttpTransport::class, fn(): OutboundHttpTransport => new GuzzleOutboundHttpTransport(
            $this->app->make(CurrentExecutionContext::class),
        ));
        $this->app->bind(InputValidator::class, fn(): InputValidator => new InputValidator(
            $this->app,
            $this->app->make(CurrentExecutionContext::class),
        ));
        $this->app->bind(
            InstallationExecutionHost::class,
            fn(): InstallationExecutionHost => new InstallationExecutionHost(dirname(__DIR__)),
        );
        $this->app->bind(AuditContractHost::class, fn(): AuditContractHost => new AuditContractHost(
            $this->app->make(PDO::class),
            $this->app->make(CurrentExecutionContext::class),
        ));
        $this->app->bind(OperationLogService::class, fn(): OperationLogService => new OperationLogService(
            $this->app->make(AuditContractHost::class),
        ));
        $this->app->bind(ExternalTenantAudit::class, fn(): ExternalTenantAudit => new ThinkPhpExternalTenantAudit(
            $this->app->make(AuditContractHost::class),
        ));
    }

    private function registerAuthentication(): void
    {
        $this->app->bind(DemoAccountPolicy::class, fn(): DemoAccountPolicy => new DemoAccountPolicy(
            $this->app->make(PDO::class),
            (string)(getenv('PEANUT_DEMO_MODE') ?: '') === 'enabled',
            array_values(array_filter([
                (string)(getenv('ADMIN_INITIAL_EMAIL') ?: ''),
                (string)(getenv('PLATFORM_INITIAL_EMAIL') ?: ''),
                (string)(getenv('PEANUT_DEMO_TENANT_A_EMAIL') ?: ''),
                (string)(getenv('PEANUT_DEMO_TENANT_B_EMAIL') ?: ''),
            ], static fn(string $email): bool => trim($email) !== '')),
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
        $this->app->bind(AdminLoginAttemptService::class, fn(): AdminLoginAttemptService => new AdminLoginAttemptService(
            (int)Config::get('admin_auth.password_error_times', 5),
            (int)Config::get('admin_auth.lock_minutes', 30),
        ));
        $this->app->bind(UserTokenService::class, fn(): UserTokenService => new UserTokenService(
            (string)Config::get('jwt.secret', ''),
            (int)Config::get('jwt.expire', 0),
        ));
    }

    private function registerAuthorization(): void
    {
        $this->app->bind(AdminPermissionPolicy::class, fn(): AdminPermissionPolicy =>
            CoreServiceOverrides::adminPermissionPolicy());
        $this->app->bind(AdminMenuPersistence::class, ThinkPhpAdminMenuPersistence::class);
        $this->app->bind(AdminAuthorizationService::class, fn(): AdminAuthorizationService => new AdminAuthorizationService(
            new NativeAdminPrincipalRepository($this->app->make(PDO::class)),
            $this->app->make(CoreTenantModuleAdminBridge::class),
            $this->app->make(AdminMenuPersistence::class),
            $this->app->make(AdminPermissionPolicy::class),
        ));
        $this->app->bind(AdminAuthorizationQuery::class, fn(): AdminAuthorizationQuery => $this->app->make(AdminAuthorizationService::class));
        $this->app->bind(CoreTenantModuleAdminBridge::class, fn(): CoreTenantModuleAdminBridge => new CoreTenantModuleAdminBridge(
            $this->app->make(PDO::class),
            $this->app->make(PdoModuleGovernanceProvider::class),
            $this->app->make(AdminMenuPersistence::class),
        ));
        $this->app->bind(RoleAdministrationRuntime::class, fn(): RoleAdministrationRuntime => new RoleAdministrationRuntime(
            $this->app->make(PDO::class),
            new RoleAdminService($this->app->make(PDO::class)),
            $this->app->make(AdminAuthorizationService::class),
            $this->app->make(AdminMenuPersistence::class),
        ));
        $this->app->bind(MenuPermissionUsageQuery::class, fn(): MenuPermissionUsageQuery => new MenuPermissionUsageQuery(
            $this->app->make(PDO::class),
        ));
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
        $this->app->bind(AdminApiAccessRegistry::class, function (): AdminApiAccessRegistry {
            $routes = Config::get('admin_api_access', []);
            return new AdminApiAccessRegistry(
                (int)Config::get('admin_api_access.version', 0),
                is_array($routes) ? $routes : [],
            );
        });
    }

    private function registerStorage(): void
    {
        $this->app->bind(StorageCredentialResolver::class, FailClosedStorageCredentialResolver::class);
        $this->app->bind(StorageRepository::class, fn(): StorageRepository => new StorageRepository(
            $this->app->make(PDO::class),
        ));
        $this->app->bind(StorageDriverFactory::class, fn(): StorageDriverFactory => new StorageDriverFactory(
            $this->app->make(StorageCredentialResolver::class),
            $this->app->make(OutboundHttpTransport::class),
            $this->app->make(AliyunStorageClientFactory::class),
            $this->app->make(QcloudStorageClientFactory::class),
            $this->app->make(CurrentExecutionContext::class),
            $this->app,
        ));
        $this->app->bind(StorageService::class, fn(): StorageService => new StorageService(
            $this->app->make(StorageRepository::class),
            $this->app->make(StorageDriverFactory::class),
            (string)Config::get('jwt.secret', ''),
            (string)$this->app->request->domain(),
        ));
        $this->app->bind(FileService::class, fn(): FileService => new FileService(
            $this->app->make(StorageService::class),
            (string)$this->app->request->domain(),
        ));
        $this->app->bind(ProductAssetReferenceService::class, fn(): ProductAssetReferenceService => new ProductAssetReferenceService(
            $this->app->make(FileService::class),
            (string)$this->app->request->domain(),
        ));
        $this->app->bind(StorageConfigurationService::class, fn(): StorageConfigurationService => new StorageConfigurationService(
            $this->app->make(StorageRepository::class),
            $this->app->make(AuditContractHost::class),
        ));
    }

    private function registerPlatform(): void
    {
        $this->app->bind(TenantSettingsBootstrapCommands::class, fn(): TenantSettingsBootstrapCommands =>
            TenantSettingsBootstrapRuntimeFactory::forProvisioning($this->app->make(PDO::class)));
        $this->app->bind(
            TenantApplicationBootstrapPersistence::class,
            ThinkPhpTenantApplicationBootstrapPersistence::class,
        );
        $this->app->bind(DefaultTenantContextResolver::class, fn(): DefaultTenantContextResolver => new DefaultTenantContextResolver(
            $this->app->make(PDO::class),
        ));
        $this->app->bind(TenantEntryBindingResolver::class, function (): TenantEntryBindingResolver {
            $mode = DeploymentMode::fromConfiguredValue(Config::get('deployment.mode'));
            $defaultSystem = $mode === DeploymentMode::Standalone
                && (bool)Config::get('deployment.public_default_tenant_fallback', true)
                ? fn(string $actor, string $operation, string $operationId) => $this->app
                    ->make(DefaultTenantContextResolver::class)
                    ->system($actor, $operation, $operationId)
                : null;
            return new TenantEntryBindingResolver($this->app->make(PDO::class), $defaultSystem);
        });
        $this->app->bind(ApplicationHostPolicy::class, fn(): ApplicationHostPolicy => new ApplicationHostPolicy(
            (string)Config::get('deployment.mode', ''),
            self::hostList((string)Config::get('deployment.platform_hosts', '')),
            self::hostList((string)Config::get('deployment.tenant_admin_hosts', '')),
            $this->app->make(TenantEntryBindingResolver::class),
        ));
        $this->app->bind(DataScopePolicy::class, function (): DataScopePolicy {
            $mode = DeploymentMode::fromConfiguredValue(Config::get('deployment.mode'));
            return match ($mode) {
                DeploymentMode::MultiTenant => new MultiTenantDataScopePolicy(
                    $this->app->make(CurrentExecutionContext::class),
                ),
                DeploymentMode::Standalone => new StandaloneDataScopePolicy(
                    $this->app->make(CurrentExecutionContext::class),
                ),
                default => throw new \RuntimeException('DEPLOYMENT_MODE_UNCONFIGURED'),
            };
        });
        $this->app->bind(TenantIdentityQuery::class, fn(): TenantIdentityQuery => new TenantIdentityQuery(
            $this->app->make(PDO::class),
        ));
        $this->app->bind(ModuleExecutionBoundary::class, function (): ModuleExecutionBoundary {
            return new ModuleExecutionBoundary(
                $this->app->make(PDO::class),
                $this->app->make(CurrentExecutionContext::class),
            );
        });
        $this->app->bind(PlatformRuntimeFactory::class, function (): PlatformRuntimeFactory {
            $moduleConfig = Config::get('modules', []);
            if (!is_array($moduleConfig)) {
                throw new \RuntimeException('MODULE_REGISTRY_UNAVAILABLE');
            }
            $trustedModuleKeyConfig = Config::get('module_packages.trusted_ed25519_keys', []);
            if (!is_array($trustedModuleKeyConfig)) {
                throw new \RuntimeException('MODULE_TRUST_CONFIGURATION_INVALID');
            }
            return new PlatformRuntimeFactory(
                $this->app->make(PDO::class),
                $this->app->make(AuditContractHost::class),
                $this->app->make(\app\Modules\Official\Notification\Contracts\NotificationBootstrapCommands::class),
                $this->app->make(\app\Modules\Official\Task\Contracts\TaskBootstrapCommands::class),
                $this->app->make(ExecutionContextStore::class),
                $this->app->make(TenantSettingsBootstrapCommands::class),
                $this->app->make(TenantApplicationBootstrapPersistence::class),
                (string)Config::get('platform_auth.identifier_hmac_key', ''),
                $moduleConfig,
                $trustedModuleKeyConfig,
            );
        });
        $this->app->bind(PlatformOpsRuntimeFactory::class, function (): PlatformOpsRuntimeFactory {
            $moduleConfig = Config::get('modules', []);
            if (!is_array($moduleConfig)) {
                throw new \RuntimeException('MODULE_REGISTRY_UNAVAILABLE');
            }
            $trustedKeys = [];
            foreach ((array)Config::get('module_packages.trusted_ed25519_keys', []) as $keyId => $encoded) {
                $decoded = is_string($encoded) ? base64_decode($encoded, true) : false;
                if (is_string($keyId) && is_string($decoded)
                    && strlen($decoded) === SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES) {
                    $trustedKeys[$keyId] = $decoded;
                }
            }
            return new PlatformOpsRuntimeFactory(
                $this->app->make(PDO::class),
                $this->app->make(AuditContractHost::class),
                dirname(__DIR__, 2),
                $moduleConfig,
                $trustedKeys,
            );
        });
        $this->app->bind(PdoModuleGovernanceProvider::class, fn(): PdoModuleGovernanceProvider => $this->app
            ->make(PlatformOpsRuntimeFactory::class)
            ->moduleGovernance());
        $this->app->bind(ApplicationRuntimeStatusProvider::class, fn(): ApplicationRuntimeStatusProvider => $this->app
            ->make(PlatformOpsRuntimeFactory::class)
            ->runtimeStatusProvider());
        $this->app->bind(\app\platform\service\ops\PlatformDiagnosticBundleService::class, fn(): \app\platform\service\ops\PlatformDiagnosticBundleService => $this->app
            ->make(PlatformOpsRuntimeFactory::class)
            ->diagnostics(
                (string)Config::get('deployment.mode', ''),
                (bool)Config::get('app.app_debug', false),
            ));
        $this->app->bind(PlatformOpsApplicationService::class, function (): PlatformOpsApplicationService {
            $runtime = $this->app->make(PlatformOpsRuntimeFactory::class);
            return new PlatformOpsApplicationService(
                $runtime->status(),
                $runtime->runtimeStatusProvider(),
                $runtime->providerQualifications(trim((string)Config::get('platform_auth.identifier_hmac_key', ''))),
                $runtime->maintenance(),
                $runtime->diagnostics(
                    (string)Config::get('deployment.mode', ''),
                    (bool)Config::get('app.app_debug', false),
                ),
                $this->app->make(AuditContractHost::class),
                $runtime->tasks(),
                $runtime->upgrades(),
                $runtime->moduleOperations(),
                $runtime->backups(),
            );
        });
        $this->app->bind(\app\common\service\dict\DictionaryRuntime::class, function (): \app\common\service\dict\DictionaryRuntime {
            $tenant = new \app\common\service\dict\ThinkPhpTenantDictionaryProvider();
            $system = new \app\common\service\dict\ThinkPhpSystemDictionaryProvider();
            return new \app\common\service\dict\DictionaryRuntime(
                new \PeanutAdmin\Kernel\Dictionary\Application\DictionaryService($tenant, $tenant, $system),
                $system,
            );
        });
        $this->app->bind(\app\common\service\tenant\TenantSettingService::class, fn(): \app\common\service\tenant\TenantSettingService => new \app\common\service\tenant\TenantSettingService(
            new \app\common\service\tenant\ThinkPhpTenantSettingsProvider(),
        ));
        $this->app->bind(\app\common\service\ConfigService::class, fn(): \app\common\service\ConfigService => new \app\common\service\ConfigService(
            new \app\common\service\config\ThinkPhpInstanceConfigStore(),
        ));
        $this->app->bind(CrontabCommandService::class, fn(): CrontabCommandService => new CrontabCommandService(
            (array)Config::get('console.commands', []),
            (array)Config::get('console.module_commands', []),
        ));
    }

    private function registerApplicationServices(): void
    {
        $this->app->bind(WorkbenchApplicationService::class, fn(): WorkbenchApplicationService => new WorkbenchApplicationService(
            $this->app->make(AdminAuthorizationService::class),
            $this->app->make(FileService::class),
            $this->app->make(\app\common\service\config\WebsiteConfigService::class),
            (string)Config::get('project.version', ''),
            (string)Config::get('project.based', ''),
            (array)Config::get('project.default_image', []),
        ));
        $this->app->bind(ConfigApplicationService::class, fn(): ConfigApplicationService => new ConfigApplicationService(
            $this->app->make(\app\common\service\config\TenantApplicationSettingService::class),
            $this->app->make(FileService::class),
            $this->app->make(\app\common\service\RichTextResourceService::class),
            $this->app->make(\app\common\service\config\WebsiteConfigService::class),
            (string)Config::get('project.default_image.user_avatar', ''),
        ));
        $this->app->bind(ThinkPhpGeneratorMetadata::class, ThinkPhpGeneratorMetadata::class);
        $this->app->bind(GeneratorApplicationService::class, fn(): GeneratorApplicationService => new GeneratorApplicationService(
            $this->app->make(GeneratorImportPersistence::class),
            $this->app->make(\app\common\persistence\TransactionalExecution::class),
            $this->app->make(ThinkPhpGeneratorMetadata::class),
            $this->databasePrefix(),
        ));
        $this->app->bind(IndexApplicationService::class, fn(): IndexApplicationService => new IndexApplicationService(
            $this->app->make(TenantIdentityQuery::class),
            $this->app->make(\app\common\service\config\TenantApplicationSettingService::class),
            $this->app->make(PublicArticleQueries::class),
            $this->app->make(\app\common\service\RichTextResourceService::class),
            $this->app->make(\app\common\service\decoration\DecorationReadService::class),
            $this->app->make(\app\common\service\config\WebsiteConfigService::class),
            (string)Config::get('project.version', ''),
            [
                'enabled' => $this->app->make(DemoAccountPolicy::class)->enabled(),
                'tenant_a_host' => (string)(getenv('PEANUT_DEMO_TENANT_A_HOST') ?: ''),
                'tenant_b_host' => (string)(getenv('PEANUT_DEMO_TENANT_B_HOST') ?: ''),
                'shared_hosts' => self::hostList((string)Config::get('deployment.tenant_admin_hosts', '')),
                'tenant_a_email' => (string)(getenv('PEANUT_DEMO_TENANT_A_EMAIL') ?: ''),
                'tenant_b_email' => (string)(getenv('PEANUT_DEMO_TENANT_B_EMAIL') ?: ''),
                'password' => (string)(getenv('PEANUT_DEMO_SHARED_PASSWORD') ?: ''),
            ],
        ));
        $this->app->bind(MemberLoginApplicationService::class, fn(): MemberLoginApplicationService => new MemberLoginApplicationService(
            $this->app->make(\app\Modules\Official\Member\Contracts\MemberIdentityCommands::class),
            $this->app->make(\app\Modules\Official\Notification\Contracts\VerificationCodeCommands::class),
            $this->app->make(\app\common\service\config\TenantApplicationSettingService::class),
            $this->app->make(FileService::class),
            $this->app->make(UserTokenService::class),
            (string)Config::get('project.default_image.user_avatar', ''),
        ));
    }

    public function boot(): void
    {
        $policy = $this->app->make(DataScopePolicy::class);
        if (!$policy instanceof DataScopePolicy) {
            throw new \LogicException('DATA_SCOPE_POLICY_UNAVAILABLE');
        }
        Model::maker(static function (Model $model) use ($policy): void {
            if ($model instanceof TenantOwnedModel) {
                $model->setDataScopePolicy($policy);
            }
        });
    }

    private function database(): PDO
    {
        $pdo = Db::connect()->connect();
        if (!$pdo instanceof PDO) {
            throw new \RuntimeException('APPLICATION_DATABASE_UNAVAILABLE');
        }
        return $pdo;
    }

    private function databasePrefix(): string
    {
        $connection = (string)Config::get('database.default', 'mysql');
        return (string)Config::get('database.connections.' . $connection . '.prefix', '');
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

    /** @return list<string> */
    private static function hostList(string $hosts): array
    {
        return array_values(array_filter(array_map('trim', explode(',', $hosts))));
    }
}
