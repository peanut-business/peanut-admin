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
$targetRelease = $fixture['target_release'] ?? null;
$expect(is_array($targetRelease), 'P0-E target scaffold release is missing');
$expect($targetRelease === [
    'version' => '1.1.4',
    'source_commit' => 'e5a20e7733dc46fc516744846c1d4d016c8fd2d0',
    'source_tree' => 'ac023dbac8c4357cff5a0967645dfe9c27118826',
    'manifest_sha256' => '07c07ed6396b7c04f088d73a2d556a4320b61c1131f950c2c1cf82bca16f217d',
    'inventory_sha256' => '35b8e3a81ccfd29a0bbe5dfbd1b8bdb69015373a2761d15fbc156f805ef2699b',
    'managed_tree_sha256' => '465455281ededc0735f66ec4ec9d86430219b3f0162164ad3d5367f0462f9011',
    'file_count' => 274,
    'application_manifest_schema' => 2,
    'default_application_version' => '0.1.0',
    'default_uniapp_version_code' => '10',
], 'P0-E target scaffold release identity changed');
$legacy = $fixture['legacy_application'] ?? null;
$expect(is_array($legacy), 'P0-E legacy application fixture is missing');
$expect(($legacy['source_commit'] ?? null) === '14412607ba36f1816e39f7117f77eea4a9e7419e', 'legacy application commit changed');
$expect(($legacy['source_tree'] ?? null) === '172865d8b8057caa8a017ac591618cd914af30a5', 'legacy application tree changed');
$expect(
    ($legacy['release_chain'] ?? null) === ['1.0.0', '1.1.0', '1.1.1', '1.1.2', '1.1.3', '1.1.4'],
    'legacy application release chain changed'
);
$expect(count($legacy['customizations'] ?? []) === 2, 'legacy application must retain two real app-owned customizations');
$expectedScenarios = [
    'standalone_fresh', 'multi_tenant_fresh', 'v1_0_forward', 'v1_1_forward',
    'migration_fault_source', 'migration_fault_restore', 'plugin_lifecycle',
    'standalone_browser', 'multi_tenant_browser',
];
$expect(array_keys($fixture['scenarios'] ?? []) === $expectedScenarios, 'P0-E scenario order or closure changed');
$expectedGroups = [
    'generated-application', 'standalone-fresh', 'multi-tenant-fresh', 'v1.0-forward',
    'v1.1-forward', 'migration-fault-restore', 'plugin-lifecycle', 'production-compose',
    'standalone-browser', 'multi-tenant-browser', 'legacy-application-upgrade',
    'legacy-application-recovery', 'upgraded-plugin-lifecycle', 'upgraded-production-compose',
    'upgraded-standalone-browser', 'upgraded-multi-tenant-browser',
];
$expect(($fixture['groups'] ?? null) === $expectedGroups, 'P0-E group order or closure changed');

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
$expect(($binding['runtime_resource_id'] ?? null) === ($registered[0]['runtime_resource_id'] ?? null), 'P0-E remote administration runtime resource diverged');
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
$expect(($plan['legacy_application'] ?? null) === $legacy, 'plan did not bind the fixed legacy application');
$expect(($plan['target_release'] ?? null) === $targetRelease, 'plan did not bind the target scaffold release');
$expect(($plan['groups'] ?? null) === $expectedGroups, 'plan did not bind the full PA-P0E-003 group closure');
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
$expect(str_contains($runnerSource, 'legacy_application_upgrade'), 'fixed legacy scaffold upgrade is not in the Gate');
$expect(str_contains($runnerSource, 'PEANUT_SCAFFOLD_FAIL_AFTER_REPLACEMENTS'), 'legacy scaffold recovery fault is not exercised');
$expect(str_contains($runnerSource, 'app_owned_proof'), 'upgraded app-owned byte proof is missing');
$expect(str_contains($runnerSource, 'upgraded_plugin_lifecycle'), 'upgraded application Plugin lifecycle is missing');
$expect(str_contains($runnerSource, 'upgraded_production_compose'), 'upgraded application service runtime is missing');
$expect(str_contains($runnerSource, 'upgraded_browser'), 'upgraded application browser runtime is missing');
$expect(
    str_contains($runnerSource, 'managed_version_surfaces')
        && str_contains($runnerSource, 'initial_versions["uniapp_version_code"]'),
    'legacy version adoption and UniApp preservation are not both enforced'
);
$expect(
    str_contains($runnerSource, 'self.production_compose_at(self.generated'),
    'generated application production Compose does not use the generated application root'
);
$expect(
    str_contains($runnerSource, 'project_root / "server/tests/fixtures/mt05/inspect.php"'),
    'application database inspection is not rooted in the qualified application'
);
$expect(
    str_contains($runnerSource, 'sha256(inspector) != sha256(INSPECTOR)'),
    'application-provided inspector identity is not fixed to the candidate fixture'
);
$expect(!str_contains($runnerSource, '["mysql"') && !str_contains($runnerSource, '["mysqldump"'), 'runner reintroduced a bare host MySQL client');
$expect(str_contains($runnerSource, 'core.excludesFile'), 'create-app does not exclude only the lease-owned runtime evidence');
$expect(str_contains($runnerSource, ':(exclude)'), 'resume cleanliness does not exclude only the lease-owned runtime evidence');
$expect(
    substr_count($runnerSource, '/var/www/peanut-admin/resources/project-resources.json') === 2,
    'generated/upgraded Compose does not mount the source-only P0-E registry for PHP and cron'
);
$expect(
    str_contains($runnerSource, 'playwright_cli.parent == lease_proof_dir'),
    'lease-owned Playwright wrapper is not removed before lease release'
);
$expect(str_contains($browserSource, 'snapshot'), 'browser runner does not capture Playwright snapshots');

$composeEnvironmentProbe = <<<'PYTHON'
import importlib.machinery
import importlib.util
import os
import sys
import tempfile
from pathlib import Path
from types import SimpleNamespace

runner_path = sys.argv[1]
loader = importlib.machinery.SourceFileLoader("p0e_runtime_qualification", runner_path)
spec = importlib.util.spec_from_loader(loader.name, loader)
module = importlib.util.module_from_spec(spec)
loader.exec_module(module)

with tempfile.TemporaryDirectory(prefix="p0e-compose-env-") as directory:
    temporary = Path(directory)
    docker = temporary / "docker"
    docker.write_text(
        "#!/bin/sh\n"
        "printf '%s|%s|%s\\n' \"$DB_NAME\" \"$DB_HOST\" \"$PEANUT_DEPLOYMENT_TARGET\"\n",
        encoding="utf-8",
    )
    docker.chmod(0o700)
    os.environ["PATH"] = f"{temporary}{os.pathsep}{os.environ['PATH']}"

    arguments = SimpleNamespace(candidate="candidate", run_id="probe", lease="lease")
    plan = {
        "candidate_tree": "tree",
        "compose_project": "peanut-p0e-probe",
        "paths": {
            "output-dir": str(temporary / "output"),
            "backup-dir": str(temporary / "backup"),
            "cache-dir": str(temporary / "cache"),
        },
    }
    runner = module.Runner(arguments, plan, {})
    runner.compose_env = temporary / "compose.env"
    runner.compose_overlay = temporary / "compose.lease-proof.yml"
    runner.compose_env.write_text(
        "DB_NAME=peanut_admin_development_p0e_probe_standalone_browser\n"
        "DB_HOST=registered-container-host\n"
        "PEANUT_DEPLOYMENT_TARGET=local-production-preview\n",
        encoding="utf-8",
    )
    runner.compose_overlay.write_text("services: {}\n", encoding="utf-8")
    result = runner.compose("config")
    expected = (
        "peanut_admin_development_p0e_probe_standalone_browser|"
        "registered-container-host|local-production-preview"
    )
    if result.stdout.strip() != expected:
        raise SystemExit(f"Compose subprocess inherited polluted environment: {result.stdout!r}")
PYTHON;
$probeCommand = '/usr/bin/env '
    . escapeshellarg('DB_NAME=peanut_admin_development') . ' '
    . escapeshellarg('DB_HOST=polluted-parent-host') . ' '
    . escapeshellarg('PEANUT_DEPLOYMENT_TARGET=production') . ' '
    . 'python3 -c ' . escapeshellarg($composeEnvironmentProbe) . ' ' . escapeshellarg($runner);
$probeOutput = [];
$probeCode = 0;
exec($probeCommand . ' 2>&1', $probeOutput, $probeCode);
$expect($probeCode === 0, 'Compose environment precedence probe failed: ' . implode("\n", $probeOutput));

echo "P0E-RUNTIME-QUALIFICATION-CONTRACT-001 passed\n";
