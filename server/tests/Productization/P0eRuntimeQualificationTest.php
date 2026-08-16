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
    'version' => '2.0.0',
    'source_commit' => '39ca2e8a3b3039516c87a6dd304c984d10be9974',
    'source_tree' => 'f430a5b29b1980d4326ae5d532a7d0c5359b7fa7',
    'manifest_sha256' => 'd806801a4defc2b5645db3f85a3a923c038abcdff60aaa3f0c43f134dce30132',
    'inventory_sha256' => '31f520ecf215ffb44a3c9f82d66ff509d91b15eaa8b35e307b817966da9e3f9c',
    'managed_tree_sha256' => 'eb8a0816d5a8c302d5177dde95616dff7f49dfdbf4d5f14379e2433d5f0d7c7a',
    'file_count' => 273,
    'application_manifest_schema' => 2,
    'default_application_version' => '0.1.0',
    'default_uniapp_version_code' => '10',
];

$expect(($fixture['schema_version'] ?? null) === 1, 'P0-E fixture schema changed');
$expect(($fixture['gate'] ?? null) === 'p0e-runtime-qualification', 'P0-E Gate identity changed');
$expect(($fixture['database_resource']['migration_count'] ?? null) === 3, 'P0-E Gate no longer fixes the 2.0 post-baseline migration set');
$expect(($fixture['database_resource']['ledger_count'] ?? null) === 4, 'P0-E Gate no longer fixes the canonical baseline ledger count');
$expect(!array_key_exists('baselines', $fixture), 'fresh-only P0-E fixture retained 1.x baselines');
$expect(!array_key_exists('legacy_application', $fixture), 'fresh-only P0-E fixture retained a legacy application');
$expect(($fixture['target_release'] ?? null) === $expectedTarget, 'P0-E target scaffold identity changed');
$expect(array_keys($fixture['scenarios'] ?? []) === $expectedScenarios, 'P0-E fresh-only scenario order or closure changed');
$expect(($fixture['groups'] ?? null) === $expectedGroups, 'P0-E fresh-only group order or closure changed');

$expectedMigrationFiles = [
    'server/database/migrations/20260816-tenant-capability-setting.sql',
    'server/database/migrations/20260816-tenant-entry-binding.sql',
    'server/database/migrations/20260816-tenant-owner-invitation.sql',
];
$expect(($releaseMetadata['migrations']['count'] ?? null) === 3, 'release metadata migration count is stale');
$expect(($releaseMetadata['migrations']['ordered_files'] ?? null) === $expectedMigrationFiles, 'release metadata migration list is stale');
$expect(
    ($releaseMetadata['migrations']['ordered_path_list_sha256'] ?? null) === '93e476d8e94853101f0eb8ef24ed526308572dae2ea58214f97c46119627a812',
    'release metadata migration path digest is stale'
);
$expect(($releaseMetadata['technical_qualification']['migrations'] ?? null) === 3, 'technical qualification migration count is stale');

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
$expect(count($plan['lease_resources'] ?? []) === 24, 'manual lease resources must have 24 exact rows');
$expect(($resourceCounts['mysql-db'] ?? null) === 5, 'claim must bind five exact fresh-only databases');
$expect(($resourceCounts['deployment-mode'] ?? null) === 2, 'claim must bind both deployment modes');
$expect(($resourceCounts['port'] ?? null) === 2, 'claim must bind both generic port conflicts');

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
$expect(str_contains($browserFixture, "page.locator('.login-form .el-select').waitFor"), 'multi-tenant browser smoke must not mistake the navbar selector for the login selector');
$expect(str_contains($browserFixture, ".el-select-dropdown:visible .el-select-dropdown__item').first().click()"), 'multi-tenant browser smoke must select a tenant before its second login submission');

echo "P0E-RUNTIME-QUALIFICATION-CONTRACT-001 passed\n";
