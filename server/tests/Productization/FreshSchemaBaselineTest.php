<?php
declare(strict_types=1);

function freshSchemaExpect(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$serverRoot = dirname(__DIR__, 2);
$schema = (string)file_get_contents($serverRoot . '/database/init.sql');
$installer = (string)file_get_contents($serverRoot . '/database/install.php');
$guard = (string)file_get_contents($serverRoot . '/database/environment-guard.php');
preg_match_all('/CREATE\s+TABLE(?:\s+IF\s+NOT\s+EXISTS)?\s+`([^`]+)`/i', $schema, $matches);
$applicationTables = array_values(array_unique($matches[1] ?? []));

foreach ([
    'pa_legacy_admin_tenant_map',
    'pa_legacy_role_tenant_map',
    'pa_legacy_dept_tenant_map',
    'pa_default_tenant_bootstrap',
    'pa_admin',
    'pa_admin_role',
    'pa_admin_dept',
    'pa_admin_jobs',
    'pa_admin_session',
    'pa_system_role',
    'pa_system_role_menu',
    'pa_dept',
] as $retiredTable) {
    $identifier = '/(?<![A-Za-z0-9_])' . preg_quote($retiredTable, '/') . '(?![A-Za-z0-9_])/';
    freshSchemaExpect(!in_array($retiredTable, $applicationTables, true), "retired table remains in canonical schema: {$retiredTable}");
    freshSchemaExpect(preg_match($identifier, $installer) !== 1, "installer still depends on retired table: {$retiredTable}");
    freshSchemaExpect(preg_match($identifier, $guard) !== 1, "environment guard still depends on retired table: {$retiredTable}");
}

freshSchemaExpect(str_contains($installer, 'KernelSchema::tableNames()'), 'installer does not create native Core schema');
freshSchemaExpect(str_contains($installer, 'BootstrapService'), 'installer does not use the native Core bootstrap service');
freshSchemaExpect(str_contains($installer, "'default'"), 'installer does not create the formal default Tenant');
freshSchemaExpect(str_contains($installer, "'core.tenant-owner'"), 'installer health contract does not verify the native owner role');
freshSchemaExpect(str_contains($installer, "'--migrate'"), 'application migration runner is not available');
$migrations = glob($serverRoot . '/database/migrations/*.sql') ?: [];
foreach ($migrations as $migration) {
    $migrationSql = (string)file_get_contents($migration);
    freshSchemaExpect(
        preg_match('/^--\s+peanut-release:\s+\d+\.\d+\.\d+\s*$/m', $migrationSql) === 1,
        'post-baseline migration is missing a release identity: ' . basename($migration)
    );
}

foreach (['information_schema', 'ALTER TABLE', 'PREPARE ', 'EXECUTE ', 'DEALLOCATE PREPARE'] as $transitionSql) {
    freshSchemaExpect(!str_contains($schema, $transitionSql), "transition SQL remains in canonical schema: {$transitionSql}");
}

freshSchemaExpect(in_array('pa_schema_migration', $applicationTables, true), 'application migration ledger is missing from the canonical schema');
foreach ([
    'pa_jobs',
    'pa_plugin_installation',
    'pa_module_migration',
    'pa_task_job',
    'pa_external_channel_binding',
    'pa_tenant_setting',
    'pa_tenant_entry_binding',
    'pa_tenant_owner_invitation',
    'pa_tenant_idempotency_record',
    'pa_system_dict_type',
    'pa_system_dict_data',
] as $table) {
    freshSchemaExpect(in_array($table, $applicationTables, true), "canonical business table missing: {$table}");
}
foreach ([
    'uk_tenant_setting_namespace',
    'uk_tenant_entry_binding',
    'uk_owner_invitation_pending_tenant',
    'uk_tenant_idempotency',
    'idx_refund_record_tenant_order_amount',
    'uk_system_dict_type_code',
    'uk_system_dict_data_type_value',
] as $index) {
    freshSchemaExpect(str_contains($schema, '`' . $index . '`'), "canonical schema index missing: {$index}");
}
foreach (['member_sex', 'member_status', 'member_channel', 'payment_status', 'refund_status'] as $dictionary) {
    freshSchemaExpect(str_contains($schema, "'{$dictionary}'"), "canonical system dictionary seed missing: {$dictionary}");
}

echo "FRESH-SCHEMA-BASELINE-001 passed\n";
