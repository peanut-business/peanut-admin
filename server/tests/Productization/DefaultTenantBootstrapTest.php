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
$runner = (string)file_get_contents($serverRoot . '/database/migrate.php');
$guard = (string)file_get_contents($serverRoot . '/database/environment-guard.php');

foreach ([
    'pa_legacy_admin_tenant_map',
    'pa_legacy_role_tenant_map',
    'pa_legacy_dept_tenant_map',
    'pa_default_tenant_bootstrap',
] as $retiredTable) {
    freshSchemaExpect(!str_contains($schema, $retiredTable), "retired table remains in canonical schema: {$retiredTable}");
    freshSchemaExpect(!str_contains($installer, $retiredTable), "installer still depends on retired table: {$retiredTable}");
    freshSchemaExpect(!str_contains($guard, $retiredTable), "environment guard still depends on retired table: {$retiredTable}");
}

freshSchemaExpect(str_contains($installer, 'KernelSchema::tableNames()'), 'installer does not create native Core schema');
freshSchemaExpect(str_contains($installer, 'BootstrapService'), 'installer does not use the native Core bootstrap service');

freshSchemaExpect(str_contains($installer, "'default'"), 'installer does not create the formal default Tenant');
freshSchemaExpect(str_contains($installer, "'core.tenant-owner'"), 'installer health contract does not verify the native owner role');
freshSchemaExpect(str_contains($runner, "[\$databaseDir . '/init.sql'"), 'migration ledger does not bind canonical init.sql');
freshSchemaExpect(str_contains($runner, 'assertAdditiveMigration'), 'post-baseline migrations are not constrained to additive changes');
freshSchemaExpect(!str_contains($runner, '--adopt-existing'), 'retired database adoption option remains active');
freshSchemaExpect((glob($serverRoot . '/database/migrations/*.sql') ?: []) === [], 'historical migrations remain active');

preg_match_all('/CREATE\s+TABLE(?:\s+IF\s+NOT\s+EXISTS)?\s+`([^`]+)`/i', $schema, $matches);
$applicationTables = array_values(array_unique($matches[1] ?? []));
freshSchemaExpect(count($applicationTables) === 69, 'canonical application table set changed unexpectedly');
foreach (['pa_schema_migration', 'pa_plugin_installation', 'pa_task_job', 'pa_external_channel_binding'] as $table) {
    freshSchemaExpect(in_array($table, $applicationTables, true), "canonical business table missing: {$table}");
}

echo "FRESH-SCHEMA-BASELINE-001 passed\n";
