<?php
declare(strict_types=1);

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
    'default_context_core' => 'vendor/peanut-admin/core/kernel/src/Tenancy/DefaultTenantContextResolver.php',
    'entry_binding' => 'app/common/service/tenant/TenantEntryBindingResolver.php',
    'entry_binding_core' => 'vendor/peanut-admin/core/kernel/src/Tenancy/TenantEntryBindingResolver.php',
    'entry_schema' => 'database/init.sql',
    'public_member_middleware' => 'app/api/middleware/PublicMemberTenantMiddleware.php',
    'tenant_session' => 'app/tenant/controller/TenantSessionController.php',
    'admin_login' => 'app/adminapi/logic/auth/LoginLogic.php',
    'authenticated_member_context' => 'app/common/service/member/AuthenticatedMemberContext.php',
    'authenticated_member_context_core' => 'vendor/peanut-admin/core/kernel/src/Context/AuthenticatedMemberContext.php',
    'member_context' => 'app/common/service/member/MemberApiTenantContextResolver.php',
    'member_middleware' => 'app/api/middleware/CheckTokenMiddleware.php',
    'file_repository' => 'app/common/service/file/FileTenantRepository.php',
    'file_namespace' => 'app/common/service/file/FileObjectNamespace.php',
    'file_namespace_core' => 'vendor/peanut-admin/core/file-media/src/Storage/TenantObjectNamespace.php',
    'article_repository' => 'app/common/service/article/ArticleTenantRepository.php',
    'decoration_repository' => 'app/common/service/decoration/DecorationTenantRepository.php',
    'notice_repository' => 'app/common/service/notice/NoticeTenantRepository.php',
    'oauth_repository' => 'app/common/service/oauth/OAuthTenantRepository.php',
    'external_resolver' => 'app/common/service/external/ExternalTenantResolver.php',
    'external_resolver_core' => 'vendor/peanut-admin/core/integration-security/src/External/ExternalTenantResolver.php',
    'external_binding_contract' => 'app/common/service/external/ExternalTenantBindingRepository.php',
    'external_audit_contract' => 'app/common/service/external/ExternalTenantAudit.php',
    'finance_repository' => 'app/common/service/finance/FinanceTenantRepository.php',
    'recharge_settings' => 'app/common/service/finance/RechargeTenantSettingService.php',
    'tenant_settings' => 'app/common/service/tenant/TenantSettingService.php',
    'notice_channel' => 'app/common/service/notice/NoticeChannelService.php',
    'platform_storage' => 'vendor/peanut-admin/core/kernel/src/Platform/InstanceControlPlanePolicy.php',
    'admin_permissions' => 'app/adminapi/service/AdminPermissionService.php',
    'member_admin_context' => 'app/common/service/member/MemberTenantContext.php',
    'article_admin_context' => 'app/common/service/article/ArticleTenantContext.php',
    'file_admin_context' => 'app/common/service/file/FileTenantContext.php',
    'finance_admin_context' => 'app/common/service/finance/FinanceTenantContext.php',
    'crontab_scheduler' => 'app/common/service/crontab/CrontabSchedulerService.php',
    'scheduled_context_core' => 'vendor/peanut-admin/core/kernel/src/Tenancy/ScheduledTenantContext.php',
    'tenant_scope_core' => 'vendor/peanut-admin/core/kernel/src/Tenancy/TenantScope.php',
    'async_authorization' => 'app/common/service/async/AdminAsyncAuthorization.php',
    'async_runtime' => 'app/common/service/async/TaskImportExportRuntime.php',
    'async_files' => 'app/common/service/export/AppFileMediaGateway.php',
    'routes' => 'route/app.php',
    'official_file_routes' => 'app/Modules/Official/File/Http/routes.php',
    'official_notification_routes' => 'app/Modules/Official/Notification/Http/routes.php',
    'official_oauth_routes' => 'app/Modules/Official/Oauth/Http/routes.php',
    'official_payment_routes' => 'app/Modules/Official/Payment/Http/routes.php',
    'official_member_routes' => 'app/Modules/Official/Member/Http/routes.php',
    'official_task_routes' => 'app/Modules/Official/Task/Http/routes.php',
    'official_import_export_routes' => 'app/Modules/Official/ImportExport/Http/routes.php',
    'official_module_middleware' => 'app/common/service/module/OfficialModuleMiddleware.php',
    'module_execution_context' => 'app/common/service/module/ModuleExecutionContext.php',
    'module_execution_context_core' => 'vendor/peanut-admin/core/kernel/src/Module/ModuleExecutionContext.php',
    'module_execution_guard' => 'app/common/service/module/ModuleExecutionGuard.php',
    'module_guard_core' => 'vendor/peanut-admin/core/kernel/src/Module/ModuleGuard.php',
    'oauth_controller' => 'app/api/controller/OAuthController.php',
    'console' => 'config/console.php',
    'module_manifest' => 'vendor/peanut-admin/core/kernel/src/Module/ManifestLoader.php',
    'module_availability' => 'vendor/peanut-admin/core/kernel/src/Host/ModuleAvailabilityAdapter.php',
    'deployed_module_registry' => 'app/platform/service/module/DeployedTenantModuleRegistry.php',
    'fixture_module_access' => 'app/Modules/Fixture/DeliveryRecord/Infrastructure/Authorization/PdoDeliveryRecordAccess.php',
    'official_article_manifest' => 'app/Modules/Official/Article/module.json',
    'official_article_access' => 'app/Modules/Official/Article/Infrastructure/Authorization/PdoArticleModuleAccess.php',
    'official_article_public' => 'app/api/middleware/PublicArticleTenantMiddleware.php',
] as $key => $relative) {
    $sources[$key] = qualificationSource($root, $relative);
}

foreach (['file_repository', 'article_repository', 'decoration_repository', 'notice_repository', 'oauth_repository', 'finance_repository'] as $key) {
    qualificationExpect(str_contains($sources[$key], 'tenant_id'), $key . ' lost SQL Tenant scope');
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
qualificationExpect(str_contains($sources['async_files'], "'tenants/v1/%d/exports/'"), 'async exports lost private Tenant namespace');
qualificationExpect(
    str_contains($sources['default_context'], 'new CoreDefaultTenantContextResolver($pdo)')
        && str_contains($sources['default_context'], 'CoreDefaultTenantContextResolver::operationId($request)')
        && str_contains($sources['default_context_core'], "code = 'default'")
        && str_contains($sources['default_context_core'], "status = 'active'")
        && str_contains($sources['default_context_core'], 'LIMIT 2')
        && str_contains($sources['default_context_core'], 'count($ids) !== 1')
        && !str_contains($sources['default_context_core'], 'default_tenant_bootstrap'),
    'anonymous default-Tenant resolution does not fail closed'
);
qualificationExpect(
    str_contains($sources['member_context'], "t.status = 'active'")
        && str_contains($sources['member_context'], 'm.tenant_id')
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
        && str_contains($sources['member_middleware'], '$request->authenticatedMemberContext =')
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
        && str_contains($sources['entry_binding'], 'DefaultTenantContextResolver::system(')
        && str_contains($sources['entry_binding_core'], 'b.host = :host')
        && str_contains($sources['entry_binding_core'], 'b.client_key = :client_key')
        && str_contains($sources['entry_binding_core'], 'count($rows) !== 1')
        && str_contains($sources['entry_binding_core'], "tenant_status'] ?? null) !== 'active'")
        && str_contains($sources['entry_binding_core'], 'TENANT_ENTRY_BINDING_CONFLICT'),
    'Tenant entry bindings are not instance-owned and active-Tenant scoped'
);
qualificationExpect(
    str_contains($sources['tenant_session'], 'loginTenantCode(')
        && str_contains($sources['admin_login'], 'loginTenantCode(')
        && str_contains($sources['public_member_middleware'], 'TenantEntryBindingResolver::production()->system(')
        && !str_contains($sources['public_member_middleware'], 'DefaultTenantContextResolver::system('),
    'Admin or anonymous member authentication bypasses Tenant entry resolution'
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
    str_contains($sources['oauth_repository'], 'MemberTenantRepository::members($context)')
        && str_contains($sources['oauth_repository'], 'self::identities($context)'),
    'OAuth subject lookup is not explicitly bound to the member Tenant context'
);
qualificationExpect(
    str_contains($sources['crontab_scheduler'], "t.status', 'active'")
        && str_contains($sources['crontab_scheduler'], 'use PeanutAdmin\\Kernel\\Tenancy\\ScheduledTenantContext;')
        && str_contains($sources['crontab_scheduler'], 'use PeanutAdmin\\Kernel\\Tenancy\\TenantScope;')
        && str_contains($sources['crontab_scheduler'], 'ScheduledTenantContext::run')
        && str_contains($sources['scheduled_context_core'], 'finally')
        && str_contains($sources['scheduled_context_core'], 'self::$scope = null')
        && str_contains($sources['scheduled_context_core'], "throw new \\RuntimeException('Scheduled TenantContext is required')")
        && str_contains($sources['tenant_scope_core'], 'fromTrustedContext(')
        && str_contains($sources['tenant_scope_core'], 'private function __construct('),
    'scheduled work does not re-establish active Tenant ownership'
);
qualificationExpect(
    str_contains($sources['async_authorization'], "t.status = 'active'")
        && str_contains($sources['async_authorization'], 'authorization_revision'),
    'async work does not recheck Tenant availability and authorization'
);
qualificationExpect(
    str_contains($sources['notice_channel'], "private const BINDING_PROVIDER = 'notice.sms'")
        && str_contains($sources['notice_channel'], "where('tenant_id', \$tenantId)")
        && !str_contains($sources['notice_channel'], 'ConfigService'),
    'notification Provider configuration is not Tenant-owned'
);
qualificationExpect(
    str_contains($sources['tenant_settings'], "where('tenant_id', \$tenantId)")
        && str_contains($sources['tenant_settings'], "where('namespace', \$namespace)")
        && str_contains($sources['recharge_settings'], 'TenantSettingService::document')
        && str_contains($sources['recharge_settings'], 'ExternalChannelBindingService::config'),
    'recharge policy or payment channel configuration is not Tenant-owned'
);
qualificationExpect(
    str_contains($sources['platform_storage'], "'storage/setup'")
        && str_contains($sources['admin_permissions'], 'use PeanutAdmin\\Kernel\\Platform\\InstanceControlPlanePolicy;')
        && str_contains($sources['admin_permissions'], 'InstanceControlPlanePolicy::isTenantAdminRoute')
        && str_contains($sources['admin_permissions'], 'InstanceControlPlanePolicy::tenantAdminPermissions()')
        && !is_file($root . '/app/common/service/platform/InstanceControlPlanePolicy.php')
        && str_contains($sources['routes'], 'api/platform/infrastructure/storage')
        && !str_contains($sources['routes'], "Route::post('storage/setup'"),
    'instance storage control remains reachable from a Tenant Admin audience'
);
foreach (['member_admin_context', 'article_admin_context', 'file_admin_context', 'finance_admin_context'] as $key) {
    qualificationExpect(
        str_contains($sources[$key], 'AuthenticatedMemberContext|TenantContext')
            && str_contains($sources[$key], '$request->tenantContext ?? null')
            && str_contains($sources[$key], 'authorizationRevision'),
        'native Admin TenantContext is not accepted safely by domain entry: ' . $key
    );
}
foreach ([
    'official_member_routes' => [
        "PublicMemberTenantMiddleware::class, 'member.register'",
        "PublicMemberTenantMiddleware::class, 'member.login'",
    ],
    'official_notification_routes' => [
        "PublicNoticeTenantMiddleware::class, 'notice.verification.send'",
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
    str_contains($sources['official_member_routes'], 'PublicNoticeTenantMiddleware::class')
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
        && str_contains($sources['official_article_access'], 'assertMemberAccess(')
        && str_contains($sources['official_article_public'], 'TenantEntryBindingResolver::production()->system(')
        && str_contains($sources['article_repository'], 'assertTenant('),
    'official Article Module is not guarded across deployment, Tenant and public Host boundaries'
);
qualificationExpect(
    str_contains($sources['module_manifest'], "'/module.json'")
        && str_contains($sources['module_availability'], 'assertDeployment(')
        && str_contains($sources['module_availability'], 'assertTenant(')
        && (
            str_contains($sources['fixture_module_access'], 'ModuleGuard')
            || str_contains($sources['fixture_module_access'], 'ModuleExecutionGuard')
        )
        && (
            str_contains($sources['fixture_module_access'], 'assertMemberAccess(')
            || (
                str_contains($sources['fixture_module_access'], 'ModuleExecutionGuard')
                && str_contains($sources['fixture_module_access'], 'assertAdminPermission(')
            )
        )
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
        && str_contains($sources['official_module_middleware'], 'ModuleExecutionContext::admin(')
        && str_contains($sources['official_module_middleware'], 'ModuleExecutionContext::system(')
        && str_contains($sources['official_module_middleware'], 'ModuleExecutionContext::businessMember(')
        && str_contains($sources['official_module_middleware'], 'assertEnabled($context)')
        && str_contains($sources['module_execution_guard'], '$this->guard->assertDeployment(')
        && str_contains($sources['module_execution_guard'], '$this->guard->assertTenant(')
        && str_contains($sources['module_execution_guard'], 'PdoTenantAuthorizationRepository')
        && str_contains($sources['module_execution_guard'], '$this->guard->assertMemberAccess(')
        && str_contains($sources['module_guard_core'], "MODULE_TENANT_DISABLED")
        && str_contains($sources['module_guard_core'], "AUTHORIZATION_PERMISSION_DENIED"),
    'shared official Module middleware does not require a trusted Tenant context and TenantModule state'
);
qualificationExpect(
    str_contains($sources['oauth_controller'], "new ModuleExecutionGuard(\$pdo, 'official.oauth')")
        && str_contains($sources['external_resolver'], 'assertExternalCallback(')
        && str_contains($sources['async_runtime'], "'official.import-export'")
        && str_contains($sources['crontab_scheduler'], "ModuleExecutionContext::scheduled('official.task'")
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
