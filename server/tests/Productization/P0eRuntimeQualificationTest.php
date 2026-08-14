<?php
declare(strict_types=1);

$root = dirname(__DIR__, 3);
$runner = $root . '/scripts/p0e-runtime-qualification';
$browser = $root . '/scripts/p0e-browser-smoke';
$fixturePath = $root . '/server/tests/fixtures/p0e-runtime-qualification/matrix.json';
$registryPath = $root . '/resources/project-resources.json';
$p0eRegistryPath = $root . '/resources/p0e-runtime-qualification.json';

$expect = static function (bool $condition, string $message): void {
    if (!$condition) throw new RuntimeException($message);
};
$run = static function (array $arguments) use ($runner): array {
    $command = escapeshellarg($runner);
    foreach ($arguments as $argument) $command .= ' ' . escapeshellarg($argument);
    $output = [];
    $code = 0;
    exec($command . ' 2>&1', $output, $code);
    return [$code, implode("\n", $output)];
};

$fixture = json_decode((string)file_get_contents($fixturePath), true, 512, JSON_THROW_ON_ERROR);
$registry = json_decode((string)file_get_contents($registryPath), true, 512, JSON_THROW_ON_ERROR);
$p0eRegistry = json_decode((string)file_get_contents($p0eRegistryPath), true, 512, JSON_THROW_ON_ERROR);
$expect(($fixture['schema_version'] ?? null) === 1, 'P0-E fixture schema changed');
$expect(($fixture['gate'] ?? null) === 'p0e-runtime-qualification', 'P0-E Gate identity changed');
$expect(($fixture['database_resource']['migration_count'] ?? null) === 54, 'P0-E Gate no longer fixes 54-current');
$expect(($fixture['baselines'] ?? null) === ['v1.0.0', 'v1.1.0'], 'P0-E forward baselines changed');
$expectedScenarios = [
    'standalone_fresh', 'multi_tenant_fresh', 'v1_0_forward', 'v1_1_forward',
    'migration_fault_source', 'migration_fault_restore', 'plugin_lifecycle',
    'standalone_browser', 'multi_tenant_browser',
];
$expect(array_keys($fixture['scenarios'] ?? []) === $expectedScenarios, 'P0-E scenario order or closure changed');

$registered = array_values(array_filter(
    $registry['resources']['databases'] ?? [],
    static fn(array $item): bool => ($item['stable_resource_id'] ?? null) === 'peanut-admin-p0e-mysql84-gate'
));
$expect(count($registered) === 1, 'P0-E resource registration is not unique');
$expect(($registered[0]['application_runtime'] ?? null) === false, 'P0-E resource became a default runtime');
$expect(($registered[0]['fallback'] ?? null) === 'none', 'P0-E resource must fail closed');
$expect(($registered[0]['allowed_scenarios'] ?? null) === $expectedScenarios, 'runner and registry scenarios diverged');
$binding = $p0eRegistry['database_administration_binding'] ?? null;
$expect(is_array($binding), 'P0-E remote administration binding is missing');
$expect(($binding['database_resource_id'] ?? null) === 'peanut-admin-p0e-mysql84-gate', 'P0-E remote administration database resource is not fixed');
$expect(($binding['allocation_resource_id'] ?? null) === ($registered[0]['allocation_resource_id'] ?? null), 'P0-E remote administration allocation diverged');
$expect(($binding['administrative_tooling_resource_id'] ?? null) === 'peanut-admin-mysql84-remote-admin-cli', 'P0-E remote administration tooling is not fixed');

$candidate = trim((string)shell_exec('git -C ' . escapeshellarg($root) . ' rev-parse HEAD'));
$runId = 'p0e' . bin2hex(random_bytes(4));
$outputPath = $root . '/output/p0e-' . $runId;
$backupPath = rtrim((string)getenv('HOME'), '/') . '/.local/state/peanut-admin/p0e-backup-' . $runId;
$cachePath = rtrim((string)getenv('HOME'), '/') . '/.cache/peanut-admin/p0e-' . $runId;
$arguments = [
    'plan', '--candidate', $candidate, '--run-id', $runId, '--lease', 'p0e-runtime-' . $runId,
    '--output-dir', $outputPath,
    '--backup-dir', $backupPath,
    '--cache-dir', $cachePath,
    '--http-port', '20190', '--docs-port', '20186',
];
[$code, $output] = $run($arguments);
$expect($code === 0, "P0-E no-resource plan failed: {$output}");
$plan = json_decode($output, true, 512, JSON_THROW_ON_ERROR);
$expect(($plan['candidate'] ?? null) === $candidate, 'plan candidate is not exact HEAD');
$expect(($plan['candidate_tree'] ?? null) === trim((string)shell_exec('git -C ' . escapeshellarg($root) . ' rev-parse HEAD^{tree}')), 'plan tree is not exact');
$expect(($plan['resource_id'] ?? null) === 'peanut-admin-p0e-mysql84-gate', 'plan resource identity changed');
$expect(($plan['environment'] ?? null) === 'development', 'plan environment changed');
$expect(($plan['endpoint'] ?? null) === '192.168.192.2:20183', 'plan endpoint changed');
$expect(($plan['database_admin_tooling']['transport'] ?? null) === 'ssh-docker-exec', 'plan does not use remote container administration');
$expect(($plan['database_admin_tooling']['mysql_command'] ?? null) === '/usr/bin/mysql', 'plan MySQL client path changed');
$expect(($plan['database_admin_tooling']['mysqldump_command'] ?? null) === '/usr/bin/mysqldump', 'plan mysqldump path changed');
$expect(($plan['database_admin_tooling']['fallback'] ?? null) === 'none; host mysql and mysqldump commands are forbidden', 'plan allowed a host database client fallback');
$expect(($plan['ports'] ?? null) === ['http' => 20190, 'docs' => 20186], 'plan ports are not registered fixed ports');
$expect(!file_exists($outputPath) && !file_exists($backupPath) && !file_exists($cachePath), 'no-resource plan created a path');

$resourcePairs = [];
$resourceCounts = [];
foreach ($plan['lease_resources'] ?? [] as $resource) {
    $resourcePairs[] = ($resource['type'] ?? '') . '=' . ($resource['value'] ?? '');
    $type = (string)($resource['type'] ?? '');
    $resourceCounts[$type] = ($resourceCounts[$type] ?? 0) + 1;
}
$expect(count($resourcePairs) === 29, 'manual lease resources must have 29 exact rows (31 with auto gate/worktree)');
$expect(($resourceCounts['mysql-db'] ?? null) === 9, 'claim must bind nine exact databases');
$expect(($resourceCounts['consumer'] ?? null) === 2, 'claim must bind host and container consumers');
$expect(($resourceCounts['deployment-mode'] ?? null) === 2, 'claim must bind both deployment modes');
$expect(($resourceCounts['port'] ?? null) === 2, 'claim must bind both generic port conflicts');
foreach ([
    'resource-id=peanut-admin-p0e-mysql84-gate', 'environment=development',
    'deployment-target=local-production-preview', 'consumer=host', 'consumer=container',
    'endpoint=192.168.192.2:20183', 'run-id=' . $runId, 'deployment-mode=standalone',
    'deployment-mode=multi-tenant', 'port=20190', 'port=20186', 'http-port=20190',
    'docs-port=20186', 'compose-project=peanut-p0e-' . $runId, 'browser-session=p0e-' . $runId,
] as $required) {
    $expect(in_array($required, $resourcePairs, true), "plan lease resource is missing: {$required}");
}
foreach ($expectedScenarios as $scenario) {
    $database = "mysql-db=peanut_admin_development_p0e_{$runId}_{$scenario}";
    $expect(in_array($database, $resourcePairs, true), "exact scenario database is missing: {$scenario}");
}
$expect(!in_array('mysql-db=peanut_admin_development', $resourcePairs, true), 'persistent development database entered the claim');
$expect(str_contains((string)($plan['lease_proof_dir'] ?? ''), '/peanut-admin-resource-leases/leases/p0e-runtime-' . $runId), 'lease proof does not bind the active common-dir lease');
$expect(($plan['lease_proof_container_path'] ?? null) === '/run/peanut-admin/resource-lease', 'container lease proof path changed');

$bad = $arguments;
$bad[array_search($runId, $bad, true)] = 'UPPER';
[$badCode] = $run($bad);
$expect($badCode !== 0, 'invalid run_id did not fail closed');
$badPorts = $arguments;
$badPorts[array_search('20190', $badPorts, true)] = '22090';
[$badPortCode] = $run($badPorts);
$expect($badPortCode !== 0, 'unregistered HTTP port did not fail closed');

$runnerSource = (string)file_get_contents($runner);
$browserSource = (string)file_get_contents($browser);
$expect(str_contains($runnerSource, 'resources != expected_resources'), 'lease verification is not an exact-set comparison');
$expect(str_contains($runnerSource, 'read_only: true'), 'container lease proof is not read-only');
$expect(str_contains($runnerSource, 'PERSISTENT_DATABASE'), 'persistent database refusal is missing');
$expect(str_contains($runnerSource, 'passed != required'), 'Gate completion closure is not enforced');
$expect(str_contains($runnerSource, 'preflight_database_admin_tooling'), 'remote database administration does not fail fast');
$expect(str_contains($runnerSource, 'remote_dump') && str_contains($runnerSource, 'remote_restore'), 'backup and restore do not use registered remote tooling');
$expect(!str_contains($runnerSource, '["mysql"') && !str_contains($runnerSource, '["mysqldump"'), 'runner reintroduced a bare host MySQL client');
$expect(str_contains($runnerSource, 'core.excludesFile'), 'create-app does not exclude only the lease-owned runtime evidence');
$expect(str_contains($runnerSource, ':(exclude)'), 'resume cleanliness does not exclude only the lease-owned runtime evidence');
$expect(str_contains($browserSource, 'snapshot'), 'browser runner does not capture Playwright snapshots');

echo "P0E-RUNTIME-QUALIFICATION-CONTRACT-001 passed\n";
