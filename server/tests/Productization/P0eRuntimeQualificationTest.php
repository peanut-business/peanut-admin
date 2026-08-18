<?php
declare(strict_types=1);

$root = dirname(__DIR__, 3);
$runner = $root . '/scripts/p0e-runtime-qualification';
$fixturePath = $root . '/server/tests/fixtures/p0e-runtime-qualification/matrix.json';
$registryPath = $root . '/resources/project-resources.json';
$p0eRegistryPath = $root . '/resources/p0e-runtime-qualification.json';
$releaseMetadataPath = $root . '/RELEASE_METADATA.json';

$expect = static function (bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
};
$run = static function (array $arguments) use ($runner): array {
    $command = escapeshellarg($runner);
    foreach ($arguments as $argument) {
        $command .= ' ' . escapeshellarg((string)$argument);
    }
    exec($command . ' 2>&1', $output, $code);
    return [$code, implode("\n", $output)];
};

$fixture = json_decode((string)file_get_contents($fixturePath), true, 512, JSON_THROW_ON_ERROR);
$registry = json_decode((string)file_get_contents($registryPath), true, 512, JSON_THROW_ON_ERROR);
$p0eRegistry = json_decode((string)file_get_contents($p0eRegistryPath), true, 512, JSON_THROW_ON_ERROR);
$releaseMetadata = json_decode((string)file_get_contents($releaseMetadataPath), true, 512, JSON_THROW_ON_ERROR);

$expectedScenarios = [
    'standalone_fresh',
    'multi_tenant_fresh',
    'plugin_lifecycle',
    'standalone_browser',
    'multi_tenant_browser',
];
$expectedGroups = [
    'generated-application',
    'standalone-fresh',
    'multi-tenant-fresh',
    'plugin-lifecycle',
    'production-compose',
    'standalone-browser',
    'multi-tenant-browser',
];
$expectedTarget = [
    'version' => '2.1.4',
    'source_commit' => '2303aba19ae7ba0128a4cf4c0f601ad4523f5631',
    'source_tree' => '121c03775c2aeb188cc28b7205cb27d7ac728089',
    'manifest_sha256' => '473b76cef2188002e32fe754f706e47f389f4b51daed50193a4c21e419c384b7',
    'inventory_sha256' => '11fdc10da37e8fe5f3d876b9db0b8e6ec936bbedce0f994658bcaa842a5caebd',
    'managed_tree_sha256' => 'dfc01750004e53985b57a19941ba4ae1eadd075db98c3f68b2626c27df0383d2',
    'file_count' => 311,
    'application_manifest_schema' => 2,
    'default_application_version' => '0.1.0',
    'default_uniapp_version_code' => '10',
];

$expect(($fixture['schema_version'] ?? null) === 1, 'P0-E fixture schema changed');
$expect(($fixture['gate'] ?? null) === 'p0e-runtime-qualification', 'P0-E Gate identity changed');
$expect(($fixture['database_resource']['migration_count'] ?? null) === 4, 'P0-E Gate no longer fixes the 2.x post-baseline migration set');
$expect(($fixture['database_resource']['ledger_count'] ?? null) === 5, 'P0-E Gate no longer fixes the canonical baseline ledger count');
$expect(!array_key_exists('baselines', $fixture), 'fresh-only P0-E fixture retained 1.x baselines');
$expect(!array_key_exists('legacy_application', $fixture), 'fresh-only P0-E fixture retained a legacy application');
$expect(($fixture['target_release'] ?? null) === $expectedTarget, 'P0-E target scaffold identity changed');
$expect(array_keys($fixture['scenarios'] ?? []) === $expectedScenarios, 'P0-E fresh-only scenario order or closure changed');
$expect(($fixture['groups'] ?? null) === $expectedGroups, 'P0-E fresh-only group order or closure changed');

$expectedMigrationFiles = [
    'server/database/migrations/20260816-tenant-capability-setting.sql',
    'server/database/migrations/20260816-tenant-entry-binding.sql',
    'server/database/migrations/20260816-tenant-owner-invitation.sql',
    'server/database/migrations/20260818-official-module-permission-ownership.sql',
];
$expect(($releaseMetadata['migrations']['count'] ?? null) === 4, 'release metadata migration count is stale');
$expect(($releaseMetadata['migrations']['ordered_files'] ?? null) === $expectedMigrationFiles, 'release metadata migration list is stale');
$expect(
    ($releaseMetadata['migrations']['ordered_path_list_sha256'] ?? null) === '3f57e741f21b3fd6bd004925a3c41922b9db724acbbb8f8b545452fa5aa46c62',
    'release metadata migration path digest is stale'
);
$expect(($releaseMetadata['technical_qualification']['migrations'] ?? null) === 4, 'technical qualification migration count is stale');

$registered = array_values(array_filter(
    $registry['resources']['databases'] ?? [],
    static fn (array $item): bool => ($item['stable_resource_id'] ?? null) === 'peanut-admin-p0e-mysql84-gate'
));
$expect(count($registered) === 1, 'P0-E resource registration is not unique');
$expect(($registered[0]['application_runtime'] ?? null) === false, 'P0-E resource became a default runtime');
$expect(($registered[0]['fallback'] ?? null) === 'none', 'P0-E resource must fail closed');
$expect(($registered[0]['allowed_scenarios'] ?? null) === $expectedScenarios, 'runner and project resource scenarios diverged');

$binding = $p0eRegistry['database_administration_binding'] ?? null;
$expect(is_array($binding), 'P0-E remote administration binding is missing');
$expect(($binding['database_resource_id'] ?? null) === 'peanut-admin-p0e-mysql84-gate', 'P0-E database resource is not fixed');
$expect(($binding['runtime_resource_id'] ?? null) === ($registered[0]['runtime_resource_id'] ?? null), 'P0-E runtime resource diverged');
$browserHosts = $p0eRegistry['browser_host_binding'] ?? null;
$expect(is_array($browserHosts), 'P0-E browser Host binding is missing');
$expect(($browserHosts['platform_host'] ?? null) === 'platform.p0e.localhost', 'P0-E Platform browser Host changed');
$expect(($browserHosts['tenant_admin_host'] ?? null) === 'admin.p0e.localhost', 'P0-E Tenant Admin browser Host changed');
$expect(($browserHosts['port'] ?? null) === 20190, 'P0-E browser Host port changed');
$expect(($browserHosts['fallback'] ?? null) === 'none', 'P0-E browser Host binding must fail closed');
$tooling = $p0eRegistry['resources']['tooling'][0] ?? null;
$expect(is_array($tooling), 'P0-E remote administration tooling is missing');
$expect(($tooling['mysql_command'] ?? null) === '/usr/bin/mysql', 'P0-E MySQL CLI path changed');
$expect(!array_key_exists('mysqldump_command', $tooling), 'fresh-only P0-E retained backup tooling');
$expect(($tooling['fallback'] ?? null) === 'none; host mysql commands are forbidden', 'P0-E tooling fallback changed');

$candidate = trim((string)shell_exec('git -C ' . escapeshellarg($root) . ' rev-parse HEAD'));
$runId = 'p0e' . bin2hex(random_bytes(4));
$outputPath = $root . '/output/p0e-' . $runId;
$cachePath = rtrim((string)getenv('HOME'), '/') . '/.cache/peanut-admin/p0e-' . $runId;
$arguments = [
    'plan',
    '--candidate', $candidate,
    '--run-id', $runId,
    '--lease', 'p0e-runtime-' . $runId,
    '--http-port', '20190',
    '--docs-port', '20186',
    '--output-dir', $outputPath,
    '--cache-dir', $cachePath,
];
[$code, $output] = $run($arguments);
$expect($code === 0, "P0-E no-resource plan failed: {$output}");
$plan = json_decode($output, true, 512, JSON_THROW_ON_ERROR);
$expect(($plan['candidate'] ?? null) === $candidate, 'plan candidate is not exact HEAD');
$expect(($plan['resource_id'] ?? null) === 'peanut-admin-p0e-mysql84-gate', 'plan resource identity changed');
$expect(($plan['environment'] ?? null) === 'development', 'plan environment changed');
$expect(($plan['endpoint'] ?? null) === '192.168.192.2:20183', 'plan endpoint changed');
$expect(($plan['target_release'] ?? null) === $expectedTarget, 'plan did not bind the 2.0 scaffold release');
$expect(($plan['groups'] ?? null) === $expectedGroups, 'plan did not bind the fresh-only closure');
$expect(!array_key_exists('legacy_application', $plan), 'plan retained a legacy application');
$expect(!array_key_exists('backup-dir', $plan['paths'] ?? []), 'plan retained a recovery backup path');
$expect(!file_exists($outputPath) && !file_exists($cachePath), 'no-resource plan created a path');

$resourceCounts = [];
foreach ($plan['lease_resources'] ?? [] as $resource) {
    $type = (string)($resource['type'] ?? '');
    $resourceCounts[$type] = ($resourceCounts[$type] ?? 0) + 1;
}
$expect(count($plan['lease_resources'] ?? []) === 26, 'manual lease resources must have 26 exact rows');
$expect(($resourceCounts['mysql-db'] ?? null) === 5, 'claim must bind five exact fresh-only databases');
$expect(($resourceCounts['deployment-mode'] ?? null) === 2, 'claim must bind both deployment modes');
$expect(($resourceCounts['port'] ?? null) === 2, 'claim must bind both generic port conflicts');
$expect(($resourceCounts['browser-host'] ?? null) === 2, 'claim must bind the separate browser Host boundaries');

$runnerSource = (string)file_get_contents($runner);
$unsupportedRunnerFragments = [
    'ensure_legacy_source',
    'create_legacy_application',
    'legacy_application_upgrade',
    'legacy_application_recovery',
    'def forward(',
    'migration_fault_restore',
    '--adopt-existing',
    'SCAFFOLD_UPGRADE',
    'upgraded_plugin_lifecycle',
    'upgraded_production_compose',
    'upgraded_browser',
    'mysqldump',
    'remote_dump',
    'remote_restore',
];
foreach ($unsupportedRunnerFragments as $fragment) {
    $expect(!str_contains($runnerSource, $fragment), "fresh-only runner retained unsupported code: {$fragment}");
}
$runStart = strpos($runnerSource, '    def run(self) -> None:');
$runEnd = strpos($runnerSource, '    def preserve_failure', $runStart === false ? 0 : $runStart);
$expect($runStart !== false && $runEnd !== false, 'runner group closure is unavailable');
$runClosure = substr($runnerSource, $runStart, $runEnd - $runStart);
foreach ($expectedGroups as $group) {
    $expect(str_contains($runClosure, 'run_group("' . $group . '"'), "runner omitted group {$group}");
}
$expect(!str_contains($runClosure, 'forward') && !str_contains($runClosure, 'legacy') && !str_contains($runClosure, 'recovery'), 'runner closure retained a legacy qualification group');
$expect(str_contains($runnerSource, 'self.generated,') && str_contains($runnerSource, 'plugin_lock_restored_sha256'), 'Plugin lifecycle is not exercised in the generated application');
$expect(str_contains($runnerSource, 'passed != required'), 'Gate completion closure is not enforced');
$expect(str_contains($runnerSource, 'preflight_database_admin_tooling'), 'remote database administration does not fail fast');
$expect(str_contains($runnerSource, 'prepare_database_credentials()'), 'P0-E runner does not synchronize the registered database credential source');
$expect(str_contains($runnerSource, 'runtime-credentials.env'), 'P0-E runner does not retain per-run browser credentials for resume');
$expect(str_contains($runnerSource, 'resources != expected_resources'), 'lease verification is not an exact-set comparison');
$expect(str_contains($runnerSource, 'read_only: true'), 'container lease proof is not read-only');
$expect(str_contains($runnerSource, 'PERSISTENT_DATABASE'), 'persistent database refusal is missing');
$expect(!str_contains($runnerSource, '["mysql"'), 'runner reintroduced a bare host MySQL client');

$pluginFixture = (string)file_get_contents($root . '/server/fixtures/plugin-module-lifecycle/run.php');
$expect(str_contains($pluginFixture, "upgrade('fixture.delivery-record', true)"), 'Plugin upgrade dry-run capability left the Gate fixture');
$expect(str_contains($pluginFixture, "rollbackPlan('fixture.delivery-record')"), 'Plugin rollback-plan capability left the Gate fixture');
$expect(str_contains($pluginFixture, "uninstall('fixture.delivery-record')"), 'Plugin preserve-data uninstall capability left the Gate fixture');

$browserFixture = (string)file_get_contents($root . '/server/tests/fixtures/p0e-runtime-qualification/browser-smoke.js');
$expect(str_contains($browserFixture, "await page.locator('input').nth(0).fill(adminEmail);"), 'browser smoke must submit an email in both deployment modes');
$expect(str_contains($browserFixture, 'P0E_BROWSER_TENANT_ADMIN_URL') && str_contains($browserFixture, 'P0E_BROWSER_PLATFORM_URL'), 'browser smoke must use separate Tenant Admin and Platform Hostnames');
$expect(str_contains($browserFixture, '${platformUrl}/platform/'), 'browser smoke must enter the standalone Platform frontend');
$expect(str_contains($browserFixture, "getByText('概览', { exact: true }).first()"), 'browser smoke must wait for the visible Platform overview label');
$expect(str_contains($browserFixture, "page.locator('.login-form .el-select').waitFor"), 'multi-tenant browser smoke must not mistake the navbar selector for the login selector');
$expect(str_contains($browserFixture, ".el-select-dropdown:visible .el-select-dropdown__item').first().click()"), 'multi-tenant browser smoke must select a tenant before its second login submission');

echo "P0E-RUNTIME-QUALIFICATION-CONTRACT-001 passed\n";
