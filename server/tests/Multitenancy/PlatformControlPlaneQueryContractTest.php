<?php
declare(strict_types=1);

function platformQueryExpect(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$serverRoot = dirname(__DIR__, 2);
$queryPath = $serverRoot . '/app/platform/query/PlatformControlPlaneQueryService.php';
$querySource = (string)file_get_contents($queryPath);
$controllerSource = (string)file_get_contents(
    $serverRoot . '/app/platform/controller/PlatformControlPlaneQueryController.php'
);
$routesSource = (string)file_get_contents($serverRoot . '/route/app.php');

preg_match_all('/\b(?:FROM|JOIN)\s+(pa_[a-z0-9_]+)/i', $querySource, $matches);
$tables = array_values(array_unique(array_map('strtolower', $matches[1] ?? [])));
sort($tables);
$allowed = [
    'pa_account',
    'pa_credential',
    'pa_member_role',
    'pa_module_installation',
    'pa_permission',
    'pa_platform_audit_event',
    'pa_platform_operator',
    'pa_platform_operator_role',
    'pa_platform_role',
    'pa_platform_role_permission',
    'pa_role',
    'pa_tenant_member',
    'pa_tenant_module',
];
sort($allowed);
platformQueryExpect($tables === $allowed, 'Platform query table boundary changed: ' . implode(', ', $tables));
platformQueryExpect(
    str_contains($querySource, 'FROM pa_module_installation mi')
        && str_contains($querySource, 'LEFT JOIN pa_tenant_module tm')
        && str_contains($querySource, "COALESCE(tm.status, 'not_enabled')"),
    'Platform Module catalog no longer includes installed modules before first Tenant enablement'
);

$genericTenantList = strpos($routesSource, "api/platform/tenants',");
platformQueryExpect($genericTenantList !== false, 'generic Tenant list route is missing');
foreach ([
    'operators' => 'platform.operator.read',
    'roles' => 'platform.role.read',
    'permissions' => 'platform.permission.read',
    'audit' => 'platform.audit.read',
    'moduleStates' => 'platform.tenant.read',
    'owner' => 'core.tenant-owner',
] as $method => $permissionOrRole) {
    platformQueryExpect(
        str_contains($querySource, "function {$method}(")
            && str_contains($querySource, $permissionOrRole),
        "Platform query contract missing: {$method}"
    );
    platformQueryExpect(
        str_contains($controllerSource, "function {$method}()"),
        "Platform query controller method missing: {$method}"
    );
}
foreach (['pa_member', 'pa_article', 'pa_recharge_order', 'pa_config', 'pa_file'] as $businessTable) {
    platformQueryExpect(
        !in_array($businessTable, $tables, true),
        "Platform query crossed into a Tenant business table: {$businessTable}"
    );
}
foreach ([
    "api/platform/tenants/detail",
    "api/platform/tenants/invitations",
    "api/platform/tenants/owner",
    "api/platform/tenants/modules",
] as $specificRoute) {
    $specificPosition = strpos($routesSource, $specificRoute);
    platformQueryExpect(
        $specificPosition !== false && $specificPosition < $genericTenantList,
        "generic Tenant list shadows specific route: {$specificRoute}"
    );
}

echo "PLATFORM-CONTROL-PLANE-QUERY-CONTRACT-001 passed\n";
