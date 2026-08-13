<?php
declare(strict_types=1);

use PeanutAdmin\Kernel\Persistence\Schema\KernelSchema;

require dirname(__DIR__, 2) . '/vendor/autoload.php';

function pluginMigrationExpect(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function pluginMigrationTableSql(string $sql, string $table): string
{
    $matched = preg_match(
        '/CREATE TABLE `' . preg_quote($table, '/') . '` \(.*?\n\) ENGINE=.*?;/s',
        $sql,
        $matches
    );
    pluginMigrationExpect($matched === 1, "missing lifecycle table: {$table}");
    return $matches[0];
}

function pluginMigrationNormalize(string $sql): string
{
    return (string)preg_replace('/\s+/', ' ', trim(rtrim($sql, ';')));
}

$path = dirname(__DIR__, 2) . '/database/migrations/20260814_plugin_module_lifecycle.sql';
$sql = (string)file_get_contents($path);
pluginMigrationExpect($sql !== '', 'Plugin lifecycle migration is unavailable');

$requiredTables = [
    'pa_plugin_installation',
    'pa_plugin_module',
    'pa_module_migration',
    'pa_permission',
    'pa_protected_resource',
    'pa_target_type',
    'pa_resource_operation',
    'pa_resource_operation_target_type',
    'pa_resource_operation_permission',
    'pa_data_condition_definition',
    'pa_resource_operation_condition',
    'pa_menu_definition',
    'pa_setting_definition',
    'pa_setting_deployment_value',
    'pa_setting_tenant_value',
    'pa_setting_target_value',
];
foreach ($requiredTables as $table) {
    pluginMigrationTableSql($sql, $table);
}

$permissionSql = pluginMigrationTableSql($sql, 'pa_permission');
pluginMigrationExpect(
    pluginMigrationNormalize($permissionSql) === pluginMigrationNormalize(KernelSchema::createSql('pa_permission')),
    'pa_permission lifecycle schema drifted from Core KernelSchema'
);
$permissionOffset = strpos($sql, 'CREATE TABLE `pa_permission`');
foreach (['pa_resource_operation_target_type', 'pa_resource_operation_permission', 'pa_menu_definition'] as $table) {
    pluginMigrationExpect(
        $permissionOffset !== false && $permissionOffset < strpos($sql, "CREATE TABLE `{$table}`"),
        "pa_permission must precede {$table}"
    );
}
foreach (['deployment', 'tenant', 'target'] as $scope) {
    pluginMigrationExpect(
        str_contains($sql, "CONSTRAINT `chk_setting_{$scope}_storage`"),
        "settings {$scope} storage constraint is missing"
    );
}

echo "PLUGIN-LIFECYCLE-MIGRATION-CONTRACT-001 passed\n";
