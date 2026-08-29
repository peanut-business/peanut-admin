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
$settingProvider = (string)file_get_contents($serverRoot . '/app/common/service/tenant/ThinkPhpTenantSettingsProvider.php');
$rechargeService = (string)file_get_contents($serverRoot . '/app/common/service/finance/RechargeTenantSettingService.php');
$adminLogic = (string)file_get_contents($serverRoot . '/app/Modules/Official/Payment/Application/RechargeSettingApplicationService.php');
$apiLogic = (string)file_get_contents($serverRoot . '/app/api/application/RechargeApplicationService.php');

foreach (['`tenant_id`', '`namespace`', '`config_json`', 'uk_tenant_setting_namespace',
    'fk_tenant_setting_tenant'] as $marker) {
    expectRechargeTenantSetting(str_contains($schema, $marker), 'Tenant setting schema missing: ' . $marker);
}
foreach (["where('tenant_id', \$tenantId)", "where('namespace', \$namespace)",
    "\$revision = (int)\$row['revision'] + 1"] as $marker) {
    expectRechargeTenantSetting(str_contains($settingProvider, $marker), 'Tenant setting owner invariant missing: ' . $marker);
}
expectRechargeTenantSetting(
    str_contains($settingService, 'implements TenantSettingsQuery, TenantSettingsCommands'),
    'Tenant setting query/command contract is missing'
);
foreach ([$adminLogic, $apiLogic] as $source) {
    expectRechargeTenantSetting(
        str_contains($source, 'RechargeTenantSettingService') && !str_contains($source, 'ConfigService'),
        'recharge runtime still reads global configuration'
    );
}
foreach (['TenantSettingsRuntimeFactory::service()->get', 'TenantSettingsRuntimeFactory::service()->replace',
    'PaymentChannelGrantService::channelConfigured', 'defaultScene', 'enabledScenes'] as $marker) {
    expectRechargeTenantSetting(str_contains($rechargeService, $marker), 'Tenant recharge invariant missing: ' . $marker);
}

echo "RECHARGE-TENANT-SETTING-001 passed\n";
