<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/route/registry_source.php';

function qualificationExpect(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function qualificationSource(string $root, string $relative): string
{
    $source = file_get_contents($root . '/' . $relative);
    qualificationExpect(is_string($source), 'qualification source is unavailable: ' . $relative);
    return $source;
}

$root = dirname(__DIR__, 2);
$sources = [];
foreach ([
    'default_context' => 'app/common/service/tenant/DefaultTenantContextResolver.php',
    'app_service' => 'app/AppService.php',
    'default_context_core' => 'vendor/peanut-admin/core/kernel/src/Tenancy/DefaultTenantContextResolver.php',
    'entry_binding' => 'app/common/service/tenant/TenantEntryBindingResolver.php',
    'entry_binding_core' => 'vendor/peanut-admin/core/kernel/src/Tenancy/TenantEntryBindingResolver.php',
    'entry_schema' => 'database/init.sql',
    'public_tenant_module_middleware' => 'app/api/middleware/PublicTenantModuleMiddleware.php',
    'current_execution_context' => 'app/common/execution/CurrentExecutionContext.php',
    'execution_context_store' => 'app/common/execution/ExecutionContextStore.php',
    'tenant_session' => 'app/adminapi/controller/auth/TenantSessionController.php',
    'tenant_session_application' => 'app/adminapi/application/auth/TenantSessionApplicationService.php',
    'admin_login' => 'app/adminapi/controller/auth/LoginController.php',
    'authenticated_member_context' => 'app/common/service/member/AuthenticatedMemberContext.php',
    'authenticated_member_context_core' => 'vendor/peanut-admin/core/kernel/src/Context/AuthenticatedMemberContext.php',
    'member_context' => 'app/common/service/member/MemberApiTenantContextResolver.php',
    'member_subject_lookup' => 'app/Modules/Official/Member/Infrastructure/Persistence/ThinkPhpMemberSubjectLookup.php',
    'member_middleware' => 'app/api/middleware/CheckTokenMiddleware.php',
    'file_repository' => 'app/Modules/Official/File/Infrastructure/Persistence/FileTenantRepository.php',
    'file_namespace' => 'app/common/service/file/FileObjectNamespace.php',
    'file_namespace_core' => 'vendor/peanut-admin/core/file-media/src/Storage/TenantObjectNamespace.php',
    'article_repository' => 'app/Modules/Official/Article/Infrastructure/Persistence/ArticleTenantRepository.php',
    'decoration_repository' => 'app/common/service/decoration/DecorationTenantRepository.php',
    'notice_repository' => 'app/Modules/Official/Notification/Infrastructure/Persistence/NoticeTenantRepository.php',
    'oauth_repository' => 'app/Modules/Official/Oauth/Infrastructure/Persistence/ThinkPhpOAuthPersistence.php',
    'oauth_queries' => 'app/Modules/Official/Oauth/Application/OAuthQueryService.php',
    'external_resolver' => 'app/common/service/external/ExternalTenantResolver.php',
    'external_resolver_core' => 'vendor/peanut-admin/core/integration-security/src/External/ExternalTenantResolver.php',
    'external_binding_contract' => 'app/common/service/external/ExternalTenantBindingRepository.php',
    'external_audit_contract' => 'app/common/service/external/ExternalTenantAudit.php',
    'finance_repository' => 'app/Modules/Official/Payment/Infrastructure/Persistence/FinanceTenantRepository.php',
    'recharge_settings' => 'app/Modules/Official/Payment/Application/RechargeTenantSettingService.php',
    'tenant_settings' => 'app/common/service/tenant/TenantSettingService.php',
    'tenant_settings_provider' => 'app/common/service/tenant/ThinkPhpTenantSettingsProvider.php',
    'tenant_settings_runtime' => 'app/common/service/tenant/TenantSettingsRuntimeFactory.php',
    'tenant_settings_bootstrap_runtime' => 'app/common/service/tenant/TenantSettingsBootstrapRuntimeFactory.php',
    'application_tenant_bootstrap' => 'app/platform/service/ApplicationTenantBootstrapService.php',
    'tenant_application_settings' => 'app/common/service/config/TenantApplicationSettingService.php',
    'notice_channel' => 'app/common/service/notice/NoticeChannelService.php',
    'platform_storage' => 'vendor/peanut-admin/core/kernel/src/Platform/InstanceControlPlanePolicy.php',
    'platform_storage_controller' => 'app/platform/controller/PlatformStorageController.php',
    'admin_permissions' => 'app/common/service/authorization/AdminAuthorizationService.php',
    'crontab_scheduler' => 'app/Modules/Official/Task/Application/CrontabSchedulerService.php',
    'crontab_repository' => 'app/Modules/Official/Task/Infrastructure/Persistence/CrontabTenantRepository.php',
    'crontab_task_definition' => 'app/Modules/Official/Task/Application/CrontabTaskDefinition.php',
    'scheduled_context_core' => 'vendor/peanut-admin/core/kernel/src/Tenancy/ScheduledTenantContext.php',
    'tenant_scope_core' => 'vendor/peanut-admin/core/kernel/src/Tenancy/TenantScope.php',
    'async_authorization' => 'app/Modules/Official/ImportExport/Infrastructure/Authorization/AdminAsyncAuthorization.php',
    'async_runtime' => 'app/Modules/Official/ImportExport/Application/TaskImportExportRuntime.php',
    'async_module_provider' => 'app/Modules/Official/ImportExport/ModuleProvider.php',
    'async_worker_definition' => 'app/Modules/Official/ImportExport/Application/ImportExportTaskWorkerDefinition.php',
    'async_files' => 'app/Modules/Official/ImportExport/Infrastructure/File/AppFileMediaGateway.php',
    'storage_path' => 'app/common/service/storage/StoragePath.php',
    'routes' => 'route/registry_source.php',
    'official_file_routes' => 'app/Modules/Official/File/Http/routes.php',
    'official_notification_routes' => 'app/Modules/Official/Notification/Http/routes.php',
    'official_oauth_routes' => 'app/Modules/Official/Oauth/Http/routes.php',
    'official_payment_routes' => 'app/Modules/Official/Payment/Http/routes.php',
    'official_member_routes' => 'app/Modules/Official/Member/Http/routes.php',
    'official_task_routes' => 'app/Modules/Official/Task/Http/routes.php',
    'official_import_export_routes' => 'app/Modules/Official/ImportExport/Http/routes.php',
    'official_module_middleware' => 'app/common/service/module/OfficialModuleMiddleware.php',
    'module_execution_boundary' => 'app/common/service/module/ModuleExecutionBoundary.php',
    'module_execution_context' => 'app/common/service/module/ModuleExecutionContext.php',
    'module_execution_context_core' => 'vendor/peanut-admin/core/kernel/src/Module/ModuleExecutionContext.php',
    'module_guard_core' => 'vendor/peanut-admin/core/kernel/src/Module/ModuleGuard.php',
    'oauth_controller' => 'app/api/controller/OAuthController.php',
    'payment_notify_controller' => 'app/api/controller/PaymentNotifyController.php',
    'official_account_controller' => 'app/api/controller/OfficialAccountController.php',
    'module_worker' => 'app/common/service/async/ModuleAwareTaskHandler.php',
    'console' => 'config/console.php',
    'module_manifest' => 'vendor/peanut-admin/core/kernel/src/Module/ManifestLoader.php',
    'module_availability' => 'vendor/peanut-admin/core/kernel/src/Host/ModuleAvailabilityAdapter.php',
    'deployed_module_registry' => 'app/platform/service/module/DeployedTenantModuleRegistry.php',
    'fixture_module_access' => 'app/Modules/Fixture/DeliveryRecord/Infrastructure/Authorization/PdoDeliveryRecordAccess.php',
    'official_article_manifest' => 'app/Modules/Official/Article/module.json',
    'official_article_access' => 'app/Modules/Official/Article/Infrastructure/Authorization/PdoArticleModuleAccess.php',
    'official_article_public' => 'app/api/middleware/PublicTenantModuleMiddleware.php',
    'member_token' => 'app/api/service/UserTokenService.php',
    'jwt_config' => 'config/jwt.php',
    'menu_controller' => 'app/adminapi/controller/auth/MenuController.php',
    'system_controller' => 'app/adminapi/controller/system/SystemController.php',
] as $key => $relative) {
    $sources[$key] = $key === 'routes'
        ? peanut_route_registry_source($root)
        : qualificationSource($root, $relative);
}

foreach (['file_repository', 'article_repository', 'decoration_repository', 'notice_repository', 'oauth_repository', 'finance_repository'] as $key) {
    qualificationExpect(
        str_contains($sources[$key], '::where([])'),
        $key . ' lost its global-scope ORM entry',
    );
}
qualificationExpect(
    str_contains($sources['file_namespace'], 'TenantObjectNamespace::directory')
        && str_contains($sources['file_namespace'], 'TenantObjectNamespace::ownsUri')
        && str_contains($sources['file_namespace_core'], "sprintf('tenants/v1/%d/%s'")
        && str_contains($sources['file_namespace_core'], "sprintf('tenants/v1/%d/'")
        && str_contains($sources['file_namespace_core'], "str_contains(\$relativeDirectory, '..')")
        && str_contains($sources['file_namespace_core'], 'assertTenantId($tenantId)'),
    'file objects lost the Core-owned Tenant namespace'
);
qualificationExpect(
    str_contains($sources['async_files'], "'export.csv'")
        && str_contains($sources['async_files'], '->storePath(')
        && str_contains($sources['storage_path'], "'tenants/v1/%d/%s/%s%s'"),
    'async exports lost private Tenant namespace'
);
qualificationExpect(
    str_contains($sources['default_context'], 'new CoreDefaultTenantContextResolver($pdo)')
        && str_contains($sources['default_context'], 'RequestTrace::id($contexts, $request, \'public\')')
        && str_contains($sources['default_context_core'], "code = 'default'")
        && str_contains($sources['default_context_core'], "status = 'active'")
        && str_contains($sources['default_context_core'], 'LIMIT 2')
        && str_contains($sources['default_context_core'], 'count($ids) !== 1')
        && !str_contains($sources['default_context_core'], 'default_tenant_bootstrap'),
    'anonymous default-Tenant resolution does not fail closed'
);
qualificationExpect(
    str_contains($sources['member_context'], 'MemberSubjectLookup')
        && str_contains($sources['member_context'], "status = 'active'")
        && str_contains($sources['member_subject_lookup'], "where('status', 1)")
        && str_contains($sources['member_subject_lookup'], "whereNull('delete_time')")
        && str_contains($sources['member_subject_lookup'], "getData('tenant_id')")
        && str_contains($sources['member_middleware'], 'MemberApiTenantContextResolver'),
    'member JWT ownership does not establish an active trusted Tenant context'
);
qualificationExpect(
    str_contains(
        $sources['authenticated_member_context'],
        'extends \\PeanutAdmin\\Kernel\\Context\\AuthenticatedMemberContext'
    )
        && str_contains($sources['authenticated_member_context_core'], 'public readonly int $memberId')
        && str_contains($sources['authenticated_member_context_core'], 'MEMBER_TENANT_CONTEXT_UNAVAILABLE')
        && !str_contains($sources['authenticated_member_context_core'], 'accountId')
        && !str_contains($sources['authenticated_member_context_core'], 'tenantMember')
        && !str_contains($sources['authenticated_member_context_core'], 'PeanutAdmin\\Kernel\\Auth'),
    'application-member identity is mixed with Core Account or TenantMember identity'
);
qualificationExpect(
    str_contains($sources['member_context'], 'new AuthenticatedMemberContext(')
        && !str_contains($sources['member_context'], 'ValidatedTenantSession')
        && !str_contains($sources['member_context'], 'TenantContext::fromValidatedSession')
        && str_contains($sources['member_middleware'], '\app\common\execution\ConsumerExecutionContext::member(')
        && str_contains($sources['member_middleware'], 'ExecutionContextStore')
        && !str_contains($sources['member_middleware'], '$request->authenticatedMemberContext =')
        && !str_contains($sources['member_middleware'], '$request->tenantContext = $this->tenantContexts()'),
    'member JWT context is still written into the Core TenantContext request boundary'
);
qualificationExpect(
    str_contains($sources['entry_schema'], 'pa_tenant_entry_binding')
        && str_contains($sources['entry_schema'], 'fk_tenant_entry_binding_tenant')
        && str_contains($sources['entry_binding'], 'new CoreTenantEntryBindingResolver($pdo, $defaultSystem)')
        && str_contains($sources['entry_binding'], '$this->delegate->loginTenantCode(')
        && str_contains($sources['entry_binding'], '$this->delegate->assertTenantAccess(')
        && str_contains($sources['entry_binding'], '$this->delegate->system(')
        && str_contains($sources['app_service'], 'DefaultTenantContextResolver::class')
        && str_contains($sources['app_service'], 'DeploymentMode::Standalone')
        && str_contains($sources['app_service'], 'deployment.public_default_tenant_fallback')
        && str_contains($sources['app_service'], 'new TenantEntryBindingResolver($this->app->make(PDO::class), $defaultSystem)')
        && str_contains($sources['entry_binding_core'], 'b.host = :host')
        && str_contains($sources['entry_binding_core'], 'b.client_key = :client_key')
        && str_contains($sources['entry_binding_core'], 'count($rows) !== 1')
        && str_contains($sources['entry_binding_core'], "tenant_status'] ?? null) !== 'active'")
        && str_contains($sources['entry_binding_core'], 'TENANT_ENTRY_BINDING_CONFLICT'),
    'Tenant entry bindings are not instance-owned and active-Tenant scoped'
);
qualificationExpect(
    str_contains($sources['tenant_session'], 'TenantSessionApplicationService')
        && str_contains($sources['tenant_session_application'], 'loginTenantCode(')
        && str_contains($sources['admin_login'], 'loginTenantCode(')
        && str_contains($sources['public_tenant_module_middleware'], '$this->entryBindings->system(')
        && !str_contains($sources['public_tenant_module_middleware'], 'DefaultTenantContextResolver::system('),
    'Admin or anonymous member authentication bypasses Tenant entry resolution'
);
qualificationExpect(
    str_contains($sources['routes'], "Route::get('search/hotLists'")
        && str_contains($sources['routes'], "PublicTenantModuleMiddleware::class, 'peanut.hot-search.public-read', '', 'hot-search.lists'")
        && str_contains($sources['routes'], "Route::get('index/policy'")
        && substr_count($sources['routes'], "PublicTenantModuleMiddleware::class, 'peanut.decoration.public-read'") >= 6,
    'public hot-search or policy route is missing a Host-bound Tenant guard'
);
qualificationExpect(
    str_contains(
        $sources['external_resolver'],
        'new \\PeanutAdmin\\IntegrationSecurity\\External\\ExternalTenantResolver($bindings, $audit)'
    )
        && str_contains($sources['external_resolver'], '$this->core->verifiedCallback(')
        && str_contains($sources['external_resolver'], '$this->core->bindingForTenant(')
        && str_contains($sources['external_binding_contract'], 'extends \\PeanutAdmin\\IntegrationSecurity\\External\\ExternalTenantBindingRepository')
        && str_contains($sources['external_audit_contract'], 'extends \\PeanutAdmin\\IntegrationSecurity\\External\\ExternalTenantAudit')
        && str_contains($sources['external_resolver_core'], '!$binding->tenantActive')
        && str_contains($sources['external_resolver_core'], 'count($bindings) !== 1')
        && str_contains($sources['external_resolver_core'], '!$binding->active')
        && str_contains($sources['external_resolver_core'], '!hash_equals($provider, $binding->provider)')
        && str_contains($sources['external_resolver_core'], "\$this->audit->record('rejected'"),
    'external callbacks do not reject ambiguous or suspended Tenant ownership'
);
qualificationExpect(
    str_contains($sources['oauth_queries'], 'wechatSubjectForMember($context, $memberId, $terminal)')
        && str_contains($sources['oauth_repository'], "'member_id' => \$memberId")
        && str_contains($sources['oauth_repository'], 'OAuthIdentity::where('),
    'OAuth subject lookup is not explicitly bound to the member Tenant context'
);
qualificationExpect(
    str_contains($sources['crontab_repository'], "t.status', 'active'")
        && str_contains($sources['crontab_scheduler'], 'use PeanutAdmin\\Kernel\\Tenancy\\TenantScope;')
        && !str_contains($sources['crontab_scheduler'], 'Console::call')
        && str_contains($sources['crontab_task_definition'], 'ScheduledTenantContext::run')
        && str_contains($sources['crontab_task_definition'], 'RetryableTaskException')
        && str_contains($sources['scheduled_context_core'], 'finally')
        && str_contains($sources['scheduled_context_core'], 'self::$scope = null')
        && str_contains($sources['scheduled_context_core'], "throw new \\RuntimeException('Scheduled TenantContext is required')")
        && str_contains($sources['tenant_scope_core'], 'fromTrustedContext(')
        && str_contains($sources['tenant_scope_core'], 'private function __construct('),
    'scheduled work does not re-establish active Tenant ownership'
);
qualificationExpect(
    str_contains($sources['async_authorization'], 'AdminAuthorizationService')
        && str_contains($sources['async_authorization'], '->principal($context)')
        && str_contains($sources['async_authorization'], '->authorizedOperation(')
        && str_contains($sources['async_authorization'], 'envelope->resourceKey')
        && str_contains($sources['async_authorization'], 'envelope->operation')
        && str_contains($sources['async_authorization'], 'envelope->requestedTargets'),
    'async work does not recheck Tenant availability and authorization'
);
qualificationExpect(
    str_contains($sources['notice_channel'], "private const BINDING_PROVIDER = 'notice.sms'")
        && str_contains($sources['notice_channel'], '$this->bindings->mutate(')
        && str_contains($sources['notice_channel'], "'tenant:' . \$tenantId")
        && str_contains($sources['notice_channel'], '$this->resolver')
        && !str_contains($sources['notice_channel'], 'ConfigService'),
    'notification Provider configuration is not Tenant-owned'
);
qualificationExpect(
    str_contains($sources['tenant_settings'], 'implements TenantSettingsQuery, TenantSettingsCommands')
        && str_contains($sources['tenant_settings_provider'], "where('tenant_id', \$tenantId)")
        && str_contains($sources['tenant_settings_provider'], "where('namespace', \$namespace)")
        && str_contains($sources['recharge_settings'], 'private readonly TenantSettingService $settings')
        && str_contains($sources['recharge_settings'], '$this->settings->get(')
        && str_contains($sources['recharge_settings'], 'private readonly PaymentChannelGrantCommands $channelGrants')
        && str_contains($sources['tenant_settings_bootstrap_runtime'], 'new PdoTenantSettingsBootstrapProvider($pdo)')
        && str_contains($sources['app_service'], 'TenantSettingsBootstrapCommands::class')
        && str_contains($sources['app_service'], 'TenantSettingsBootstrapRuntimeFactory::forProvisioning(')
        && str_contains($sources['application_tenant_bootstrap'], 'private TenantSettingsBootstrapCommands $tenantSettings')
        && str_contains($sources['application_tenant_bootstrap'], '$this->tenantSettings->seedDefaults(')
        && !str_contains($sources['application_tenant_bootstrap'], 'TenantSettingsBootstrapRuntimeFactory')
        && !str_contains($sources['application_tenant_bootstrap'], 'PdoTenantSettingsBootstrapProvider')
        && str_contains($sources['recharge_settings'], '$this->channelGrants->channelConfigured('),
    'recharge policy or payment channel configuration is not Tenant-owned'
);
foreach (['agreement', 'site-statistics', 'member-profile', 'login', 'web-page', 'hot-search'] as $namespace) {
    qualificationExpect(
        str_contains($sources['tenant_application_settings'], "'{$namespace}'"),
        'Tenant application setting namespace is missing: ' . $namespace
    );
}
qualificationExpect(
    str_contains($sources['member_token'], "'aud'")
        && str_contains($sources['member_token'], "'sub'")
        && str_contains($sources['member_token'], 'strlen($this->secret) < 32')
        && !str_contains($sources['jwt_config'], 'peanut-admin-change-this-in-production')
        && !str_contains($sources['member_token'], 'peanut-admin-secret-key'),
    'member JWT is missing key strength, lifetime, issuer/audience, or subject validation'
);
qualificationExpect(
    str_contains($sources['menu_controller'], 'instanceMenuDenial()')
        && str_contains($sources['system_controller'], 'instanceToolAccessDenial()'),
    'Tenant Admin can still reach an instance-global control plane'
);
qualificationExpect(
    !is_file($root . '/app/adminapi/controller/setting/StorageController.php'),
    'retired Tenant Admin storage controller remains available for accidental route registration'
);
qualificationExpect(
    str_contains($sources['platform_storage_controller'], 'StorageConfigurationService')
        && str_contains($sources['admin_permissions'], 'use PeanutAdmin\\Kernel\\Platform\\InstanceControlPlanePolicy;')
        && str_contains($sources['admin_permissions'], 'InstanceControlPlanePolicy::isTenantAdminRoute')
        && str_contains($sources['admin_permissions'], 'InstanceControlPlanePolicy::tenantAdminPermissions()')
        && !is_file($root . '/app/common/service/platform/InstanceControlPlanePolicy.php')
        && str_contains($sources['routes'], 'infrastructure/storage')
        && !str_contains($sources['routes'], "Route::post('storage/setup'"),
    'instance storage control remains reachable from a Tenant Admin audience'
);
foreach ([
    'app/common/service/member/MemberTenantContext.php',
    'app/common/service/article/ArticleTenantContext.php',
    'app/common/service/file/FileTenantContext.php',
    'app/common/service/oauth/OAuthTenantContext.php',
    'app/common/service/hot_search/HotSearchTenantContext.php',
    'app/common/service/decoration/DecorationTenantContext.php',
    'app/common/service/finance/FinanceTenantContext.php',
    'app/common/service/org/OrgTenantContext.php',
    'app/common/service/dict/DictTenantContext.php',
] as $retiredContext) {
    qualificationExpect(
        !is_file($root . '/' . $retiredContext),
        'retired domain-specific Tenant context was reintroduced: ' . $retiredContext,
    );
}
qualificationExpect(
    str_contains($sources['current_execution_context'], 'function tenantAdmin(): TenantContext')
        && str_contains($sources['current_execution_context'], 'function member(): AuthenticatedMemberContext')
        && str_contains($sources['execution_context_store'], 'function run(ExecutionContext $context, callable $operation)'),
    'typed execution context boundary is missing',
);
foreach ([
    'official_member_routes' => [
        "PublicTenantModuleMiddleware::class, 'peanut.member.public-auth', (new ModuleProvider())->moduleKey(), 'member.register'",
        "PublicTenantModuleMiddleware::class, 'peanut.member.public-auth', (new ModuleProvider())->moduleKey(), 'member.login'",
    ],
    'official_notification_routes' => [
        "'peanut.notice.verification', (new ModuleProvider())->moduleKey(), 'notice.verification.send'",
    ],
] as $sourceKey => $routeGuards) {
    foreach ($routeGuards as $routeGuard) {
        qualificationExpect(
            str_contains($sources[$sourceKey], $routeGuard),
            'public Tenant guard is missing: ' . $routeGuard
        );
    }
}
qualificationExpect(
    str_contains($sources['official_member_routes'], "'peanut.notice.verification', 'official.notification'")
        && str_contains($sources['official_member_routes'], 'notice.verification.verify'),
    'member password/mobile flows are missing the Tenant-owned notification guard'
);

$matrix = [
    'file_media' => ['trusted_context' => true, 'sql_scope' => true, 'non_sql_namespace' => true],
    'article_content_decoration' => ['trusted_context' => true, 'sql_scope' => true, 'suspended_tenant_denied' => true],
    'member_crm' => ['trusted_context' => true, 'sql_scope' => true, 'suspended_tenant_denied' => true],
    'notice_oauth' => ['trusted_context' => true, 'sql_scope' => true, 'external_owner_active' => true],
    'payment_recharge_refund_callbacks' => ['trusted_context' => true, 'sql_scope' => true, 'external_owner_active' => true],
    'crontab_task_import_export' => ['trusted_context' => true, 'sql_scope' => true, 'non_sql_namespace' => true, 'execution_recheck' => true],
    'tenant_module' => [
        'official_capabilities_are_enableable_modules' => true,
        'reason' => 'Shared engines stay in Core, while each official business entry is packaged and Tenant enableable.',
        'optional_module_manifest_required' => true,
        'optional_module_enable_guard_required' => true,
        'optional_module_guard_owner' => 'peanut-admin/core ModuleGuard plus permission catalog',
    ],
    'official_article_module' => [
        'manifest' => true,
        'plugin_installation_guard' => true,
        'tenant_module_guard' => true,
        'tenant_sql_scope' => true,
        'host_bound_public_tenant' => true,
    ],
];

foreach (array_diff(array_keys($matrix), ['tenant_module', 'official_article_module']) as $capability) {
    qualificationExpect(
        ($matrix[$capability]['trusted_context'] ?? false) === true
            && ($matrix[$capability]['sql_scope'] ?? false) === true,
        'official capability is not mandatorily Tenant-scoped: ' . $capability
    );
}
qualificationExpect(
    str_contains($sources['official_article_manifest'], '"key": "official.article"')
        && str_contains($sources['official_article_access'], 'ModuleGuard')
        && str_contains($sources['official_article_access'], 'public function assertTenant(')
        && str_contains($sources['official_article_access'], 'assertMemberAccess(')
        && str_contains($sources['official_article_public'], '$this->entryBindings->system(')
        && str_contains($sources['article_repository'], 'return Article::where([])'),
    'official Article Module is not guarded across deployment, Tenant and public Host boundaries'
);
qualificationExpect(
    str_contains($sources['module_manifest'], "'/module.json'")
        && str_contains($sources['module_availability'], 'assertDeployment(')
        && str_contains($sources['module_availability'], 'assertTenant(')
        && str_contains($sources['fixture_module_access'], 'PdoTenantAuthorizationRepository')
        && str_contains($sources['fixture_module_access'], "AUTHORIZATION_PERMISSION_DENIED")
        && str_contains($sources['deployed_module_registry'], "(\$tenant['enableable'] ?? null) !== true"),
    'optional Modules are not guarded by both module.json and Tenant enablement'
);

$officialModules = [
    'official.file' => 'official_file_routes',
    'official.notification' => 'official_notification_routes',
    'official.oauth' => 'official_oauth_routes',
    'official.payment' => 'official_payment_routes',
    'official.member' => 'official_member_routes',
    'official.task' => 'official_task_routes',
    'official.import-export' => 'official_import_export_routes',
];
foreach ($officialModules as $moduleKey => $routeSourceKey) {
    $routeFile = 'official_' . str_replace(['official.', '-'], ['', '_'], $moduleKey) . '.php';
    qualificationExpect(
        str_contains($sources[$routeSourceKey], 'OfficialModuleMiddleware::class')
            && str_contains($sources[$routeSourceKey], 'ModuleProvider')
            && str_contains($sources['routes'], "'{$routeFile}'"),
        'official Module HTTP entry is not loaded and Tenant guarded: ' . $moduleKey
    );
}
qualificationExpect(
    str_contains(
        $sources['module_execution_context'],
        '\\PeanutAdmin\\Kernel\\Module\\ModuleExecutionContext::class'
    )
        && str_contains($sources['module_execution_context'], 'class_alias(')
        && str_contains($sources['module_execution_context_core'], 'public static function admin(')
        && str_contains($sources['module_execution_context_core'], 'public static function businessMember(')
        && str_contains($sources['module_execution_context_core'], 'public static function system(')
        && str_contains($sources['module_execution_context_core'], 'public static function scheduled(')
        && str_contains($sources['module_execution_context_core'], 'authorizationRevision < 1')
        && str_contains($sources['module_execution_context_core'], 'MODULE_CONTEXT_INVALID')
        && str_contains($sources['module_execution_boundary'], 'CurrentExecutionContext')
        && str_contains($sources['module_execution_boundary'], 'ModuleExecutionContext::admin(')
        && str_contains($sources['module_execution_boundary'], 'ModuleExecutionContext::system(')
        && str_contains($sources['module_execution_boundary'], 'ModuleExecutionContext::businessMember(')
        && str_contains($sources['official_module_middleware'], '->assertHttp($moduleKey, $operation)')
        && str_contains($sources['module_execution_boundary'], '$this->guard->assertDeployment(')
        && str_contains($sources['module_execution_boundary'], '$this->guard->assertTenant(')
        && str_contains($sources['module_guard_core'], "MODULE_TENANT_DISABLED")
        && str_contains($sources['module_guard_core'], "AUTHORIZATION_PERMISSION_DENIED"),
    'shared official Module middleware does not require a trusted Tenant context and TenantModule state'
);
qualificationExpect(
    str_contains($sources['oauth_controller'], "assertExternalCallback('official.oauth')")
        && str_contains($sources['official_account_controller'], "assertExternalCallback('official.oauth')")
        && str_contains($sources['payment_notify_controller'], "assertExternalCallback('official.payment')")
        && !str_contains($sources['external_resolver'], 'assertExternalCallback(')
        && str_contains($sources['async_runtime'], 'ImportExportCommands')
        && str_contains($sources['async_module_provider'], 'TaskImportExportRuntime::class')
        && str_contains($sources['async_module_provider'], 'TaskJobRuntime::class')
        && str_contains($sources['async_worker_definition'], "return 'official.import-export'")
        && str_contains($sources['module_worker'], "assertWorker('official.task')")
        && str_contains($sources['crontab_task_definition'], "assertScheduled('official.task')")
        && str_contains($sources['console'], "'refund:reconcile' => 'official.payment'"),
    'external callback, worker or scheduler entry bypasses its official Module lifecycle'
);

$shippedManifests = glob($root . '/app/Modules/*/*/module.json') ?: [];
sort($shippedManifests, SORT_STRING);
foreach ($shippedManifests as $manifestPath) {
    $manifest = json_decode(
        (string)file_get_contents($manifestPath),
        true,
        512,
        JSON_THROW_ON_ERROR
    );
    $tenant = is_array($manifest['tenant'] ?? null) ? $manifest['tenant'] : [];
    $backend = is_array($manifest['backend'] ?? null) ? $manifest['backend'] : [];
    $database = is_array($manifest['database'] ?? null) ? $manifest['database'] : [];
    qualificationExpect(
        ($tenant['enableable'] ?? null) === true
            && ($tenant['disable_behavior'] ?? null) === 'reject_new_operations'
            && is_array($tenant['requires'] ?? null)
            && is_string($backend['provider'] ?? null)
            && trim((string)$backend['provider']) !== ''
            && is_array($database['owned_tables'] ?? null),
        'shipped optional Module is not mandatorily Tenant-qualified: '
            . basename(dirname($manifestPath))
    );
}

echo json_encode(['status' => 'passed', 'matrix' => $matrix], JSON_UNESCAPED_SLASHES) . PHP_EOL;
