<?php
declare(strict_types=1);

function expectRechargeTenantSetting(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$serverRoot = dirname(__DIR__, 2);
$schema = (string)file_get_contents($serverRoot . '/database/init.sql');
$settingService = (string)file_get_contents($serverRoot . '/app/common/service/tenant/TenantSettingService.php');
$rechargeService = (string)file_get_contents($serverRoot . '/app/common/service/finance/RechargeTenantSettingService.php');
$adminLogic = (string)file_get_contents($serverRoot . '/app/adminapi/logic/setting/RechargeSettingLogic.php');
$apiLogic = (string)file_get_contents($serverRoot . '/app/api/logic/RechargeLogic.php');

foreach (['`tenant_id`', '`namespace`', '`config_json`', 'uk_tenant_setting_namespace',
    'fk_tenant_setting_tenant'] as $marker) {
    expectRechargeTenantSetting(str_contains($schema, $marker), 'Tenant setting schema missing: ' . $marker);
}
foreach (["where('tenant_id', \$tenantId)", "where('namespace', \$namespace)",
    "'revision' => (int)\$row['revision'] + 1"] as $marker) {
    expectRechargeTenantSetting(str_contains($settingService, $marker), 'Tenant setting owner invariant missing: ' . $marker);
}
foreach ([$adminLogic, $apiLogic] as $source) {
    expectRechargeTenantSetting(
        str_contains($source, 'RechargeTenantSettingService') && !str_contains($source, 'ConfigService'),
        'recharge runtime still reads global configuration'
    );
}
foreach (['TenantSettingService::document', 'TenantSettingService::replace',
    'ExternalChannelBindingService::config', 'defaultScene', 'enabledScenes'] as $marker) {
    expectRechargeTenantSetting(str_contains($rechargeService, $marker), 'Tenant recharge invariant missing: ' . $marker);
}

echo "RECHARGE-TENANT-SETTING-001 passed\n";
