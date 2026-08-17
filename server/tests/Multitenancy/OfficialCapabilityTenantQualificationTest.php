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
    'entry_binding' => 'app/common/service/tenant/TenantEntryBindingResolver.php',
    'entry_migration' => 'database/migrations/20260816-tenant-entry-binding.sql',
    'public_member_middleware' => 'app/api/middleware/PublicMemberTenantMiddleware.php',
    'tenant_session' => 'app/tenant/controller/TenantSessionController.php',
    'admin_login' => 'app/adminapi/logic/auth/LoginLogic.php',
    'authenticated_member_context' => 'app/common/service/member/AuthenticatedMemberContext.php',
    'member_context' => 'app/common/service/member/MemberApiTenantContextResolver.php',
    'member_middleware' => 'app/api/middleware/CheckTokenMiddleware.php',
    'file_repository' => 'app/common/service/file/FileTenantRepository.php',
    'file_namespace' => 'app/common/service/file/FileObjectNamespace.php',
    'article_repository' => 'app/common/service/article/ArticleTenantRepository.php',
    'decoration_repository' => 'app/common/service/decoration/DecorationTenantRepository.php',
    'notice_repository' => 'app/common/service/notice/NoticeTenantRepository.php',
    'oauth_repository' => 'app/common/service/oauth/OAuthTenantRepository.php',
    'external_resolver' => 'app/common/service/external/ExternalTenantResolver.php',
    'finance_repository' => 'app/common/service/finance/FinanceTenantRepository.php',
    'recharge_settings' => 'app/common/service/finance/RechargeTenantSettingService.php',
    'tenant_settings' => 'app/common/service/tenant/TenantSettingService.php',
    'notice_channel' => 'app/common/service/notice/NoticeChannelService.php',
    'platform_storage' => 'app/common/service/platform/InstanceControlPlanePolicy.php',
    'admin_permissions' => 'app/adminapi/service/AdminPermissionService.php',
    'member_admin_context' => 'app/common/service/member/MemberTenantContext.php',
    'article_admin_context' => 'app/common/service/article/ArticleTenantContext.php',
    'file_admin_context' => 'app/common/service/file/FileTenantContext.php',
    'finance_admin_context' => 'app/common/service/finance/FinanceTenantContext.php',
    'crontab_scheduler' => 'app/common/service/crontab/CrontabSchedulerService.php',
    'async_authorization' => 'app/common/service/async/AdminAsyncAuthorization.php',
    'async_files' => 'app/common/service/export/AppFileMediaGateway.php',
    'routes' => 'route/app.php',
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
qualificationExpect(str_contains($sources['file_namespace'], 'tenants/v1/%d'), 'file objects lost Tenant namespace');
qualificationExpect(str_contains($sources['async_files'], "'tenants/v1/%d/exports/'"), 'async exports lost private Tenant namespace');
qualificationExpect(
    str_contains($sources['default_context'], "Db::name('tenant')")
        && str_contains($sources['default_context'], "where('code', 'default')")
        && str_contains($sources['default_context'], "where('status', 'active')")
        && !str_contains($sources['default_context'], 'default_tenant_bootstrap'),
    'anonymous default-Tenant resolution does not fail closed'
);
qualificationExpect(
    str_contains($sources['member_context'], "t.status = 'active'")
        && str_contains($sources['member_context'], 'm.tenant_id')
        && str_contains($sources['member_middleware'], 'MemberApiTenantContextResolver'),
    'member JWT ownership does not establish an active trusted Tenant context'
);
qualificationExpect(
    str_contains($sources['authenticated_member_context'], 'public readonly int $memberId')
        && !str_contains($sources['authenticated_member_context'], 'accountId')
        && !str_contains($sources['authenticated_member_context'], 'tenantMember')
        && !str_contains($sources['authenticated_member_context'], 'PeanutAdmin\\Kernel\\Auth'),
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
    str_contains($sources['entry_migration'], 'pa_tenant_entry_binding')
        && str_contains($sources['entry_migration'], 'fk_tenant_entry_binding_tenant')
        && str_contains($sources['entry_binding'], 'b.host = :host')
        && str_contains($sources['entry_binding'], 'b.client_key = :client_key')
        && str_contains($sources['entry_binding'], "tenant_status'] ?? null) !== 'active'"),
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
    str_contains($sources['external_resolver'], '!$binding->tenantActive')
        && str_contains($sources['external_resolver'], 'count($bindings) !== 1'),
    'external callbacks do not reject ambiguous or suspended Tenant ownership'
);
qualificationExpect(
    str_contains($sources['oauth_repository'], 'MemberTenantRepository::members($context)')
        && str_contains($sources['oauth_repository'], 'self::identities($context)'),
    'OAuth subject lookup is not explicitly bound to the member Tenant context'
);
qualificationExpect(
    str_contains($sources['crontab_scheduler'], "t.status', 'active'")
        && str_contains($sources['crontab_scheduler'], 'ScheduledTenantContext::run'),
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
        && str_contains($sources['admin_permissions'], 'InstanceControlPlanePolicy::isTenantAdminRoute')
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
    "PublicMemberTenantMiddleware::class, 'member.register'",
    "PublicMemberTenantMiddleware::class, 'member.login'",
    "PublicNoticeTenantMiddleware::class, 'notice.verification.send'",
    "PublicNoticeTenantMiddleware::class, 'notice.verification.verify'",
] as $routeGuard) {
    qualificationExpect(str_contains($sources['routes'], $routeGuard), 'public Tenant guard is missing: ' . $routeGuard);
}

$matrix = [
    'file_media' => ['trusted_context' => true, 'sql_scope' => true, 'non_sql_namespace' => true],
    'article_content_decoration' => ['trusted_context' => true, 'sql_scope' => true, 'suspended_tenant_denied' => true],
    'member_crm' => ['trusted_context' => true, 'sql_scope' => true, 'suspended_tenant_denied' => true],
    'notice_oauth' => ['trusted_context' => true, 'sql_scope' => true, 'external_owner_active' => true],
    'payment_recharge_refund_callbacks' => ['trusted_context' => true, 'sql_scope' => true, 'external_owner_active' => true],
    'crontab_task_import_export' => ['trusted_context' => true, 'sql_scope' => true, 'non_sql_namespace' => true, 'execution_recheck' => true],
    'tenant_module' => [
        'application_hosts_are_enableable_modules' => false,
        'reason' => 'Application Host capabilities remain separate; only explicitly packaged official Modules use TenantModule.',
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
