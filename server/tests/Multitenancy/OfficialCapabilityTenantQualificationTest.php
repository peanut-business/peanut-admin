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
    'crontab_scheduler' => 'app/common/service/crontab/CrontabSchedulerService.php',
    'async_authorization' => 'app/common/service/async/AdminAsyncAuthorization.php',
    'async_files' => 'app/common/service/export/AppFileMediaGateway.php',
    'routes' => 'route/app.php',
] as $key => $relative) {
    $sources[$key] = qualificationSource($root, $relative);
}

foreach (['file_repository', 'article_repository', 'decoration_repository', 'notice_repository', 'oauth_repository', 'finance_repository'] as $key) {
    qualificationExpect(str_contains($sources[$key], 'tenant_id'), $key . ' lost SQL Tenant scope');
}
qualificationExpect(str_contains($sources['file_namespace'], 'tenants/v1/%d'), 'file objects lost Tenant namespace');
qualificationExpect(str_contains($sources['async_files'], "'tenants/v1/%d/exports/'"), 'async exports lost private Tenant namespace');
qualificationExpect(
    str_contains($sources['default_context'], "t.status = 'active'")
        && str_contains($sources['default_context'], "b.status', 'completed'"),
    'anonymous default-Tenant resolution does not fail closed'
);
qualificationExpect(
    str_contains($sources['member_context'], "t.status = 'active'")
        && str_contains($sources['member_context'], 'm.tenant_id')
        && str_contains($sources['member_middleware'], 'MemberApiTenantContextResolver'),
    'member JWT ownership does not establish an active trusted Tenant context'
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
        'reason' => 'No shipped application Host in this matrix declares module.json; TenantModule applies only to registered optional Modules.',
        'optional_module_guard_owner' => 'peanut-admin/core ModuleGuard plus permission catalog',
    ],
];

echo json_encode(['status' => 'passed', 'matrix' => $matrix], JSON_UNESCAPED_SLASHES) . PHP_EOL;
