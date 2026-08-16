<?php
declare(strict_types=1);

$root = dirname(__DIR__, 3);
$registryPath = $root . '/resources/project-resources.json';
$p0eRegistryPath = $root . '/resources/p0e-runtime-qualification.json';
$registryJson = (string)file_get_contents($registryPath);
$registry = json_decode($registryJson, true, 512, JSON_THROW_ON_ERROR);
$p0eRegistry = json_decode((string)file_get_contents($p0eRegistryPath), true, 512, JSON_THROW_ON_ERROR);

$expect = static function (bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
};

$expect(($registry['schema_version'] ?? null) === 1, 'resource registry schema version is invalid');
$expect(($registry['project_id'] ?? null) === 'peanut-admin', 'resource registry project id is invalid');
$expect(($registry['authority']['source'] ?? null) === 'this versioned file', 'project registry is not authoritative');
$expect(!array_key_exists('company_allocation_evidence', $registry['authority'] ?? []), 'project registry still depends on CompanyOS allocation evidence');
$expect(!str_contains($registryJson, 'CompanyOS') && !str_contains($registryJson, 'company-os'), 'project registry still references CompanyOS');

$docsDomains = array_values(array_filter(
    $registry['resources']['external_services'] ?? [],
    static fn (array $item): bool => ($item['stable_resource_id'] ?? '') === 'peanut-admin-production-docs-domain'
));
$expect(count($docsDomains) === 1, 'production documentation domain must be registered exactly once as an external service');
$docsDomain = $docsDomains[0];
$expect(($docsDomain['environments'] ?? null) === ['production'], 'documentation domain must remain production-only');
$expect(($docsDomain['host'] ?? null) === 'peanut-admin-doc.007345.xyz', 'documentation domain host changed unexpectedly');
$expect(($docsDomain['port'] ?? null) === 443, 'documentation domain port changed unexpectedly');
$expect(($docsDomain['service_type'] ?? null) === 'Cloudflare Pages documentation site', 'documentation service type changed unexpectedly');
$expect(($docsDomain['fallback'] ?? null) === 'none', 'documentation domain must fail closed');
$expect(!array_key_exists('deployment_resource_id', $docsDomain), 'documentation domain must not claim the application Docker deployment');
$docsBackupMatches = array_values(array_filter(
    $registry['resources']['backups'] ?? [],
    static fn (array $item): bool => ($item['stable_resource_id'] ?? '') === 'peanut-admin-production-docs-domain'
));
$expect($docsBackupMatches === [], 'documentation domain must not be registered as a backup resource');

$candidateDatabases = array_values(array_filter(
    $registry['resources']['databases'] ?? [],
    static fn (array $item): bool => ($item['stable_resource_id'] ?? '') === 'peanut-admin-production-candidate-mysql84'
));
$expect(count($candidateDatabases) === 1, 'production candidate database must be registered exactly once');
$candidateDatabase = $candidateDatabases[0];
$expect(($candidateDatabase['environments'] ?? null) === ['production-candidate'], 'candidate database environment changed');
$expect(($candidateDatabase['database'] ?? null) === 'peanut_admin_candidate', 'candidate database name changed');
$expect(($candidateDatabase['fresh_install_only'] ?? null) === true, 'candidate database lost the fresh-only boundary');
$expect(($candidateDatabase['container_endpoint']['host'] ?? null) === 'mysql'
    && ($candidateDatabase['container_endpoint']['port'] ?? null) === 3306, 'candidate database endpoint changed');

$candidateDomains = array_values(array_filter(
    $registry['resources']['external_services'] ?? [],
    static fn (array $item): bool => ($item['stable_resource_id'] ?? '') === 'peanut-admin-production-candidate-domains'
));
$expect(count($candidateDomains) === 1, 'production candidate domain group must be registered exactly once');
$expect(($candidateDomains[0]['hosts'] ?? null) === [
    'pa-platform.007345.xyz',
    'pa-admin.007345.xyz',
    'pa-tenant-a.007345.xyz',
    'pa-tenant-b.007345.xyz',
], 'candidate domain list changed unexpectedly');
$expect(($candidateDomains[0]['origin_endpoint']['port'] ?? null) === 18093, 'candidate origin port changed');

$localDemoDomains = array_values(array_filter(
    $registry['resources']['external_services'] ?? [],
    static fn (array $item): bool => ($item['stable_resource_id'] ?? '') === 'peanut-admin-local-multi-tenant-demo-domains'
));
$expect(count($localDemoDomains) === 1, 'local multi-tenant demo domains must be registered exactly once');
$expect(($localDemoDomains[0]['environments'] ?? null) === ['local-multi-tenant-demo'], 'local demo domains use the wrong environment');
$expect(($localDemoDomains[0]['hosts'] ?? null) === [
    'platform.peanut-admin.test',
    'admin.peanut-admin.test',
    'tenant-a.peanut-admin.test',
    'tenant-b.peanut-admin.test',
], 'local demo domain list changed unexpectedly');
$expect(($localDemoDomains[0]['tenant_entry_bindings'] ?? null) === [
    'tenant-a.peanut-admin.test' => 'tenant-a/admin-web',
    'tenant-b.peanut-admin.test' => 'tenant-b/admin-web',
], 'local demo Tenant host bindings changed unexpectedly');

$productionDeployments = array_values(array_filter(
    $registry['resources']['external_services'] ?? [],
    static fn (array $item): bool => ($item['stable_resource_id'] ?? '') === 'peanut-admin-production-deployment'
));
$expect(count($productionDeployments) === 1, 'published production deployment must be registered exactly once');
$expect(($productionDeployments[0]['environments'] ?? null) === ['production'], 'published deployment must remain production-only');
$expect(($productionDeployments[0]['deployment_root'] ?? null) === '/www/docker/peanut-admin', 'published deployment root changed');
$expect(($productionDeployments[0]['required_non_secret_environment']['PEANUT_DEPLOYMENT_TARGET'] ?? null) === 'production', 'published deployment target changed');

$candidateDeployments = array_values(array_filter(
    $registry['resources']['external_services'] ?? [],
    static fn (array $item): bool => ($item['stable_resource_id'] ?? '') === 'peanut-admin-production-candidate-deployment'
));
$expect(count($candidateDeployments) === 1, 'production candidate deployment must be registered exactly once');
$expect(($candidateDeployments[0]['environments'] ?? null) === ['production-candidate'], 'candidate deployment environment changed');
$expect(($candidateDeployments[0]['deployment_root'] ?? null) === '/www/docker/peanut-admin-candidate', 'candidate deployment root changed');
$expect(($candidateDeployments[0]['required_non_secret_environment']['PEANUT_DEPLOYMENT_TARGET'] ?? null) === 'production-candidate', 'candidate deployment target changed');
$expect(($candidateDeployments[0]['database_resource_id'] ?? null) === 'peanut-admin-production-candidate-mysql84', 'candidate deployment database changed');

$databases = array_values(array_filter(
    $registry['resources']['databases'] ?? [],
    static fn (array $item): bool => in_array('development', $item['environments'] ?? [], true)
        && ($item['application_runtime'] ?? true)
));
$expect(count($databases) === 1, 'development must select exactly one database');
$database = $databases[0];
$expect($database['stable_resource_id'] === 'peanut-admin-mysql84-development', 'development database id changed unexpectedly');
$expect($database['version'] === '8.4.10', 'development database version changed unexpectedly');
$expect($database['database'] === 'peanut_admin_development', 'development database name changed unexpectedly');
$expect($database['fallback'] === 'none', 'development database must fail closed');

foreach (['upstream_endpoint' => 'host', 'container_endpoint' => 'container'] as $key => $consumer) {
    $endpoint = $database[$key] ?? null;
    $expect(is_array($endpoint), "{$key} is missing");
    $expect(in_array($consumer, $endpoint['consumers'] ?? [], true), "{$key} consumer is invalid");
    $expect($endpoint['host'] === '192.168.192.2', "{$key} host changed unexpectedly");
    $expect($endpoint['port'] === 20183, "{$key} port changed unexpectedly");
}

$qualificationDatabases = array_values(array_filter(
    $registry['resources']['databases'] ?? [],
    static fn (array $item): bool => ($item['stable_resource_id'] ?? '') === 'peanut-admin-p0e-mysql84-gate'
));
$expect(count($qualificationDatabases) === 1, 'P0-E qualification database registration is missing');
$qualificationDatabase = $qualificationDatabases[0];
$requiredQualificationFields = [
    'purpose', 'owner', 'runtime_resource_id', 'service_type', 'schema', 'allowed_scenarios',
    'upstream_endpoint', 'container_endpoint', 'credential_ref', 'data_source',
    'freshness_requirement', 'health_check', 'claim_precondition', 'creation_policy',
    'backup_responsibility', 'cleanup_responsibility', 'failure_retention_policy',
];
foreach ($requiredQualificationFields as $field) {
    $expect(array_key_exists($field, $qualificationDatabase), "P0-E required field {$field} is missing");
}
$expect(($qualificationDatabase['application_runtime'] ?? null) === false, 'P0-E database must not be selected as an application runtime');
$expect(($qualificationDatabase['environments'] ?? null) === ['development'], 'P0-E database must stay in the development environment');
$expect(($qualificationDatabase['version'] ?? null) === '8.4.10', 'P0-E database version changed unexpectedly');
$expect(($qualificationDatabase['database'] ?? null) === 'peanut_admin_development_p0e_<run_id>_<scenario>', 'P0-E database template is invalid');
$expect(($qualificationDatabase['namespace'] ?? null) === 'peanut_admin_development_p0e_<run_id>_', 'P0-E database namespace is invalid');
$expect(($qualificationDatabase['run_id_pattern'] ?? null) === '^[a-z0-9]{1,11}$', 'P0-E run_id policy is invalid');
$expect(($qualificationDatabase['database_name_max_length'] ?? null) === 64, 'P0-E database name limit is invalid');
$expect(($qualificationDatabase['credential_ref'] ?? null) === 'mac-14:/Users/xing/.config/peanut-admin/development-db.env', 'P0-E credential reference changed unexpectedly');
$expect(($qualificationDatabase['lifecycle'] ?? null) === 'ephemeral', 'P0-E database lifecycle must be ephemeral');
$expect(($qualificationDatabase['fallback'] ?? null) === 'none', 'P0-E database must fail closed');
$expect(($qualificationDatabase['claim_precondition'] ?? '') !== '', 'P0-E claim precondition is missing');
$expect(($qualificationDatabase['creation_policy'] ?? '') !== '', 'P0-E creation policy is missing');
$expect(($qualificationDatabase['backup_responsibility'] ?? '') !== '', 'P0-E backup responsibility is missing');
$expect(($qualificationDatabase['cleanup_responsibility'] ?? '') !== '', 'P0-E cleanup responsibility is missing');
$expect(($qualificationDatabase['failure_retention_policy'] ?? '') !== '', 'P0-E failure retention policy is missing');
$expect(($qualificationDatabase['data_source'] ?? '') !== '', 'P0-E authoritative data source is missing');
$expect(($qualificationDatabase['freshness_requirement'] ?? '') !== '', 'P0-E freshness requirement is missing');
$expect(is_array($qualificationDatabase['health_check'] ?? null), 'P0-E health check is missing');

$expect(($p0eRegistry['schema_version'] ?? null) === 1, 'P0-E resource registry schema version is invalid');
$expect(($p0eRegistry['project_id'] ?? null) === 'peanut-admin', 'P0-E resource registry project id is invalid');
$expect(($p0eRegistry['gate'] ?? null) === 'p0e-runtime-qualification', 'P0-E resource registry Gate changed');
$expect(!array_key_exists('company_allocation_evidence', $p0eRegistry['authority'] ?? []), 'P0-E registry still depends on CompanyOS allocation evidence');
$expect(!array_key_exists('company_allocation_resource_id', $p0eRegistry['authority'] ?? []), 'P0-E registry still has a CompanyOS allocation identity');
$expect(!str_contains((string)json_encode($p0eRegistry), 'CompanyOS') && !str_contains((string)json_encode($p0eRegistry), 'company-os'), 'P0-E registry still references CompanyOS');
$binding = $p0eRegistry['database_administration_binding'] ?? null;
$expect(is_array($binding), 'P0-E database administration binding is missing');
$expect(($binding['database_resource_id'] ?? null) === 'peanut-admin-p0e-mysql84-gate', 'P0-E binding database resource changed');
$expect(($binding['runtime_resource_id'] ?? null) === ($qualificationDatabase['runtime_resource_id'] ?? null), 'P0-E binding and project runtime resource diverged');
$expect(($binding['administrative_tooling_resource_id'] ?? null) === 'peanut-admin-mysql84-remote-admin-cli', 'P0-E binding tooling resource changed');
$expect(($binding['database'] ?? null) === ($qualificationDatabase['database'] ?? null), 'P0-E binding database template diverged');
$expect(($binding['namespace'] ?? null) === ($qualificationDatabase['namespace'] ?? null), 'P0-E binding namespace diverged');
$expect(($binding['version'] ?? null) === ($qualificationDatabase['version'] ?? null), 'P0-E binding version diverged');
$expect(($binding['port'] ?? null) === ($qualificationDatabase['upstream_endpoint']['port'] ?? null), 'P0-E binding port diverged');
$expect(($binding['fallback'] ?? null) === 'none', 'P0-E database administration binding must fail closed');

$administrativeTools = array_values(array_filter(
    $p0eRegistry['resources']['tooling'] ?? [],
    static fn (array $item): bool => ($item['stable_resource_id'] ?? '') === 'peanut-admin-mysql84-remote-admin-cli'
));
$expect(count($administrativeTools) === 1, 'P0-E remote MySQL administration tooling registration is missing');
$administrativeTool = $administrativeTools[0];
$expect(($administrativeTool['host'] ?? null) === 'mac-14', 'P0-E administration moved off the database resource host');
$expect(($administrativeTool['version'] ?? null) === '8.4.10', 'P0-E administration client version changed');
$expect(($administrativeTool['transport'] ?? null) === 'ssh-docker-exec', 'P0-E administration transport changed');
$expect(($administrativeTool['ssh_command'] ?? null) === '/usr/bin/ssh', 'P0-E SSH command is not absolute');
$expect(($administrativeTool['docker_command'] ?? null) === '/usr/local/bin/docker', 'P0-E remote Docker command is not absolute');
$expect(($administrativeTool['container_name'] ?? null) === 'peanut-admin-mysql84-development', 'P0-E administration container changed');
$expect(($administrativeTool['mysql_command'] ?? null) === '/usr/bin/mysql', 'P0-E MySQL command is not absolute');
$expect(($administrativeTool['mysqldump_command'] ?? null) === '/usr/bin/mysqldump', 'P0-E mysqldump command is not absolute');
$expect(str_starts_with((string)($administrativeTool['container_image'] ?? ''), 'mysql:8.4.10@sha256:'), 'P0-E administration image is not immutable');
$expect(($administrativeTool['fallback'] ?? null) === 'none; host mysql and mysqldump commands are forbidden', 'P0-E administration allowed a host CLI fallback');

foreach (['upstream_endpoint' => 'host', 'container_endpoint' => 'container'] as $key => $consumer) {
    $endpoint = $qualificationDatabase[$key] ?? null;
    $expect(is_array($endpoint), "P0-E {$key} is missing");
    $expect(in_array($consumer, $endpoint['consumers'] ?? [], true), "P0-E {$key} consumer is invalid");
    $expect(($endpoint['host'] ?? null) === '192.168.192.2', "P0-E {$key} host changed unexpectedly");
    $expect(($endpoint['port'] ?? null) === 20183, "P0-E {$key} port changed unexpectedly");
}

$runSelector = static function (array $arguments) use ($root): array {
    $command = escapeshellarg($root . '/scripts/project-resource-registry');
    foreach ($arguments as $argument) {
        $command .= ' ' . escapeshellarg($argument);
    }
    $output = [];
    $exitCode = 0;
    exec($command . ' 2>&1', $output, $exitCode);
    return [$exitCode, implode("\n", $output)];
};

[$exitCode, $output] = $runSelector([
    'database-env', '--deployment-target', 'local-development', '--consumer', 'host',
]);
$expect($exitCode === 0, "default development database selection failed: {$output}");
$expect(str_contains($output, 'PEANUT_DATABASE_RESOURCE_ID=peanut-admin-mysql84-development'), 'persistent development database was not selected by default');
$expect(!str_contains($output, 'peanut-admin-p0e-mysql84-gate'), 'P0-E database was selected as the default runtime');

[$exitCode, $output] = $runSelector([
    'database-env', '--deployment-target', 'local-development', '--consumer', 'host',
    '--resource-id', 'peanut-admin-mysql84-development',
]);
$expect($exitCode === 0 && str_contains($output, 'DB_NAME=peanut_admin_development'), 'explicit fixed database resource selection failed');

[$exitCode, $output] = $runSelector([
    'database-env', '--deployment-target', 'local-development', '--consumer', 'host',
    '--resource-id', 'peanut-admin-mysql84-development', '--database-name', 'override_forbidden',
]);
$expect($exitCode !== 0, 'fixed database resource unexpectedly allowed a database name override');

$qualificationName = 'peanut_admin_development_p0e_run123_standalone_fresh';
[$exitCode, $output] = $runSelector([
    'database-env', '--deployment-target', 'local-development', '--consumer', 'host',
    '--resource-id', 'peanut-admin-p0e-mysql84-gate', '--database-name', $qualificationName,
]);
$expect($exitCode === 0, "explicit P0-E database selection failed: {$output}");
$expect(str_contains($output, 'PEANUT_DATABASE_RESOURCE_ID=peanut-admin-p0e-mysql84-gate'), 'explicit P0-E resource identity is missing');
$expect(str_contains($output, "DB_NAME={$qualificationName}"), 'explicit P0-E database name is missing');

[$exitCode, $output] = $runSelector([
    'database-env', '--deployment-target', 'local-development', '--consumer', 'host',
    '--resource-id', 'peanut-admin-p0e-mysql84-gate',
]);
$expect($exitCode !== 0, 'templated database resource unexpectedly allowed a missing database name');

[$exitCode, $output] = $runSelector([
    'database-env', '--deployment-target', 'production', '--consumer', 'container',
    '--resource-id', 'peanut-admin-p0e-mysql84-gate', '--database-name', $qualificationName,
]);
$expect($exitCode !== 0, 'P0-E database selection unexpectedly allowed production');

[$exitCode, $output] = $runSelector([
    'database-env', '--deployment-target', 'local-development', '--consumer', 'host',
    '--resource-id', 'peanut-admin-p0e-mysql84-gate',
    '--database-name', 'peanut_admin_development_intruder_run123_standalone_fresh',
]);
$expect($exitCode !== 0, 'P0-E database selection unexpectedly allowed an out-of-namespace name');

[$exitCode, $output] = $runSelector([
    'database-env', '--deployment-target', 'local-development', '--consumer', 'host',
    '--resource-id', 'peanut-admin-p0e-mysql84-gate',
    '--database-name', 'peanut_admin_development_p0e_run123_unknown_scenario',
]);
$expect($exitCode !== 0, 'templated database selection unexpectedly allowed an unknown scenario');

[$exitCode, $output] = $runSelector([
    'database-env', '--deployment-target', 'local-development', '--consumer', 'host',
    '--resource-id', 'peanut-admin-p0e-mysql84-gate',
    '--database-name', 'peanut_admin_development_p0e_UPPER_standalone_fresh',
]);
$expect($exitCode !== 0, 'templated database selection unexpectedly allowed an invalid run_id');

$selectorSource = (string)file_get_contents($root . '/scripts/project-resource-registry');
$expect(!str_contains($selectorSource, 'P0E_RESOURCE_ID'), 'resource selector still hard-codes a project resource identity');
$expect(!str_contains($selectorSource, 'P0-E database'), 'resource selector still exposes project-specific database semantics');

$rootInstructions = (string)file_get_contents($root . '/AGENTS.md');
$localStack = (string)file_get_contents($root . '/scripts/local-stack.sh');
$probe = (string)file_get_contents($root . '/scripts/local-environment-probe');
$guardSource = (string)file_get_contents($root . '/server/database/environment-guard.php');
$devCompose = (string)file_get_contents($root . '/deploy/docker-compose.dev.yml');
$hostRuntime = (string)file_get_contents($root . '/scripts/local-php-runtime');

$expect(str_contains($rootInstructions, 'resources/project-resources.json'), 'root AGENTS.md does not reference the registry');
$expect(str_contains($rootInstructions, 'resources/p0e-runtime-qualification.json'), 'root AGENTS.md does not reference the P0-E source-only registry');
$expect(str_contains($localStack, 'project-resource-registry'), 'local stack does not consume the registry');
$expect(str_contains($probe, 'resources/project-resources.json'), 'probe does not consume the registry');
$expect(str_contains($guardSource, 'PEANUT_RESOURCE_LEASE_PROOF')
    && str_contains($guardSource, '/run/peanut-admin/resource-lease'), 'P0-E guard does not require the fixed lease proof mount');
$expect(str_contains($hostRuntime, '/opt/homebrew/bin/php'), 'daily development does not use registered host PHP');
$expect(str_contains($hostRuntime, '/usr/local/bin/composer'), 'daily development does not use registered Composer');
$expect(!preg_match('/(?m)^\s{2}php:\s*$/', $devCompose), 'development Compose still defines a PHP service');
$expect(str_contains($devCompose, 'host.docker.internal'), 'development containers do not target host PHP');
$expect(str_contains($devCompose, 'NO_PROXY'), 'development containers do not bypass proxies for host PHP');
$expect(!str_contains($localStack, 'DB_HOST=192.168.192.2'), 'local stack contains a database host magic value');
$registeredPorts = [];
foreach ($registry['resources']['local_listeners'] ?? [] as $listener) {
    $registeredPorts[$listener['port_env']] = $listener['port'];
}
$expect($registeredPorts === [
    'DEV_HTTP_PORT' => 20187,
    'PHP_PORT' => 20180,
    'VITE_PORT' => 20181,
    'PLATFORM_PORT' => 20177,
    'MT_DEMO_PHP_PORT' => 20178,
    'MT_DEMO_VITE_PORT' => 20179,
    'MT_DEMO_PLATFORM_PORT' => 20176,
    'PC_PORT' => 20185,
    'MOBILE_PORT' => 20182,
    'DOCS_PORT' => 20186,
    'HTTP_PORT' => 20190,
], 'registered local listener ports do not match the Peanut Admin project block');
$redis = array_values(array_filter(
    $registry['resources']['optional_services'] ?? [],
    static fn (array $item): bool => ($item['stable_resource_id'] ?? '') === 'peanut-admin-local-redis-experiment'
));
$expect(count($redis) === 1 && $redis[0]['port_env'] === 'REDIS_PORT' && $redis[0]['port'] === 20184, 'registered Redis port is invalid');

require_once $root . '/server/database/environment-guard.php';

/** @param array<string,string> $values */
function resourceGuardSetEnvironment(array $values): void
{
    foreach ([
        'APP_ENV', 'PEANUT_DEPLOYMENT_TARGET', 'PEANUT_DATABASE_RESOURCE_ID',
        'PEANUT_DATABASE_CONSUMER', 'PEANUT_DATABASE_ENDPOINT_ID',
        'PEANUT_RESOURCE_LEASE_PROOF', 'DB_HOST', 'DB_PORT', 'DB_NAME',
        'DB_USER', 'DB_PASS', 'DEPLOYMENT_MODE',
    ] as $name) {
        putenv($name);
    }
    foreach ($values as $name => $value) {
        putenv($name . '=' . $value);
    }
}

/** @param array<string,string> $overrides @return array<string,string> */
function resourceGuardP0eEnvironment(string $runId, string $scenario, string $mode, array $overrides = []): array
{
    return array_replace([
        'APP_ENV' => 'production',
        'PEANUT_DEPLOYMENT_TARGET' => 'local-production-preview',
        'PEANUT_DATABASE_RESOURCE_ID' => 'peanut-admin-p0e-mysql84-gate',
        'DB_HOST' => '192.168.192.2',
        'DB_PORT' => '20183',
        'DB_NAME' => 'peanut_admin_development_p0e_' . $runId . '_' . $scenario,
        'DB_USER' => 'guard-test',
        'DB_PASS' => 'guard-test',
        'DEPLOYMENT_MODE' => $mode,
    ], $overrides);
}

function resourceGuardDelete(string $path): void
{
    if (!file_exists($path) && !is_link($path)) return;
    if (is_dir($path) && !is_link($path)) {
        foreach (array_diff(scandir($path) ?: [], ['.', '..']) as $entry) {
            resourceGuardDelete($path . '/' . $entry);
        }
        rmdir($path);
        return;
    }
    unlink($path);
}

/**
 * @param callable(array<string,string>&,array<string,list<string>>&):void|null $mutate
 */
function resourceGuardWriteProof(string $directory, string $runId, int $now, ?callable $mutate = null): void
{
    mkdir($directory, 0700, true);
    $worktree = '/Users/xing/Documents/company-projects/peanut-admin-p0e-runtime';
    $lease = 'p0e-runtime-' . $runId;
    $metadata = [
        'lease' => $lease,
        'owner' => 'environment-guard-test',
        'thread' => 'environment-guard-test-thread',
        'candidate' => str_repeat('a', 40),
        'gate' => 'p0e-runtime-qualification',
        'worktree' => $worktree,
        'created_at' => (string)($now - 30),
        'expires_at' => (string)($now + 3600),
        'status' => 'ACTIVE',
    ];
    $scenarios = [
        'standalone_fresh', 'multi_tenant_fresh', 'v1_0_forward', 'v1_1_forward',
        'migration_fault_source', 'migration_fault_restore', 'plugin_lifecycle',
        'standalone_browser', 'multi_tenant_browser',
    ];
    $resources = [
        'resource-id' => ['peanut-admin-p0e-mysql84-gate'],
        'environment' => ['development'],
        'deployment-target' => ['local-production-preview'],
        'consumer' => ['host', 'container'],
        'endpoint' => ['192.168.192.2:20183'],
        'run-id' => [$runId],
        'candidate-tree' => [str_repeat('b', 40)],
        'mysql-db' => array_map(
            static fn(string $scenario): string => 'peanut_admin_development_p0e_' . $runId . '_' . $scenario,
            $scenarios
        ),
        'deployment-mode' => ['standalone', 'multi-tenant'],
        'port' => ['20190', '20186'],
        'http-port' => ['20190'],
        'docs-port' => ['20186'],
        'cache-dir' => ['/Users/xing/.cache/peanut-admin/p0e-' . $runId],
        'output-dir' => [$worktree . '/output/p0e-' . $runId],
        'backup-dir' => ['/Users/xing/.local/state/peanut-admin/p0e-backup-' . $runId],
        'compose-project' => ['peanut-p0e-' . $runId],
        'browser-session' => ['p0e-' . $runId],
        'lease-proof-dir' => [
            '/Users/xing/Documents/company-projects/peanut-admin/.git/peanut-admin-resource-leases/leases/' . $lease,
        ],
        'gate' => ['p0e-runtime-qualification'],
        'worktree' => [$worktree],
    ];
    if ($mutate !== null) $mutate($metadata, $resources);
    $metadataRows = [];
    foreach ($metadata as $key => $value) $metadataRows[] = $key . "\t" . $value;
    file_put_contents($directory . '/metadata.tsv', implode("\n", $metadataRows) . "\n");
    $resourceRows = [];
    foreach ($resources as $type => $values) {
        foreach ($values as $value) {
            $resourceRows[] = hash('sha256', $type . "\t" . $value) . "\t" . $type . "\t" . $value;
        }
    }
    file_put_contents($directory . '/resources.tsv', implode("\n", $resourceRows) . "\n");
}

function resourceGuardMustFail(callable $operation, string $case): void
{
    try {
        $operation();
    } catch (RuntimeException) {
        return;
    }
    throw new RuntimeException("environment guard unexpectedly allowed {$case}");
}

$ordinaryEnvironments = [
    'development' => [
        'APP_ENV' => 'development', 'PEANUT_DEPLOYMENT_TARGET' => 'local-development',
        'PEANUT_DATABASE_RESOURCE_ID' => 'peanut-admin-mysql84-development',
        'PEANUT_DATABASE_CONSUMER' => 'host',
        'PEANUT_DATABASE_ENDPOINT_ID' => 'peanut-admin-mysql84-development-host-direct',
        'DB_HOST' => '192.168.192.2', 'DB_PORT' => '20183',
        'DB_NAME' => 'peanut_admin_development', 'DB_USER' => 'test', 'DB_PASS' => 'test',
        'DEPLOYMENT_MODE' => 'standalone',
    ],
    'local-preview' => [
        'APP_ENV' => 'production', 'PEANUT_DEPLOYMENT_TARGET' => 'local-production-preview',
        'PEANUT_DATABASE_RESOURCE_ID' => 'peanut-admin-mysql84-development',
        'DB_HOST' => '192.168.192.2', 'DB_PORT' => '20183',
        'DB_NAME' => 'peanut_admin_development', 'DB_USER' => 'test', 'DB_PASS' => 'test',
        'DEPLOYMENT_MODE' => 'standalone',
    ],
    'local-multi-tenant-demo' => [
        'APP_ENV' => 'development', 'PEANUT_DEPLOYMENT_TARGET' => 'local-multi-tenant-demo',
        'PEANUT_DATABASE_RESOURCE_ID' => 'peanut-admin-mysql84-local-multi-tenant-demo',
        'PEANUT_DATABASE_CONSUMER' => 'host',
        'PEANUT_DATABASE_ENDPOINT_ID' => 'peanut-admin-mysql84-local-multi-tenant-demo-host-direct',
        'DB_HOST' => '192.168.192.2', 'DB_PORT' => '20183',
        'DB_NAME' => 'peanut_admin_development_mtlocal01', 'DB_USER' => 'test', 'DB_PASS' => 'test',
        'DEPLOYMENT_MODE' => 'multi-tenant',
    ],
    'production' => [
        'APP_ENV' => 'production', 'PEANUT_DEPLOYMENT_TARGET' => 'production',
        'PEANUT_DATABASE_RESOURCE_ID' => 'peanut-admin-production-bundled-mysql84',
        'DB_HOST' => 'mysql', 'DB_PORT' => '3306', 'DB_NAME' => 'peanut_admin',
        'DB_USER' => 'test', 'DB_PASS' => 'test', 'DEPLOYMENT_MODE' => 'standalone',
    ],
    'production-candidate' => [
        'APP_ENV' => 'production', 'PEANUT_DEPLOYMENT_TARGET' => 'production-candidate',
        'PEANUT_DATABASE_RESOURCE_ID' => 'peanut-admin-production-candidate-mysql84',
        'DB_HOST' => 'mysql', 'DB_PORT' => '3306', 'DB_NAME' => 'peanut_admin_candidate',
        'DB_USER' => 'test', 'DB_PASS' => 'test', 'DEPLOYMENT_MODE' => 'multi-tenant',
    ],
];
foreach ($ordinaryEnvironments as $case => $environment) {
    resourceGuardSetEnvironment($environment);
    $config = guardedDatabaseConfig();
    $expect($config['database'] === $environment['DB_NAME'], "ordinary guard regression: {$case}");
}

$temporary = sys_get_temp_dir() . '/peanut-environment-guard-' . bin2hex(random_bytes(6));
$guardRunId = 'run123';
$guardNow = 2_000_000_000;
try {
    $activeProof = $temporary . '/active';
    resourceGuardWriteProof($activeProof, $guardRunId, $guardNow);
    $scenarioModes = [
        'standalone_fresh' => 'standalone', 'multi_tenant_fresh' => 'multi-tenant',
        'v1_0_forward' => 'standalone', 'v1_1_forward' => 'standalone',
        'migration_fault_source' => 'standalone', 'migration_fault_restore' => 'standalone',
        'plugin_lifecycle' => 'multi-tenant', 'standalone_browser' => 'standalone',
        'multi_tenant_browser' => 'multi-tenant',
    ];
    foreach ($scenarioModes as $scenario => $mode) {
        resourceGuardSetEnvironment(resourceGuardP0eEnvironment($guardRunId, $scenario, $mode));
        $config = guardedDatabaseConfig($activeProof, $guardNow);
        $expect($config['consumer'] === 'container', "P0-E guard did not allow exact scenario {$scenario}");
    }

    $proofMutations = [
        'expired' => static function (array &$metadata): void { $metadata['expires_at'] = '2000000000'; },
        'released' => static function (array &$metadata): void { $metadata['status'] = 'RELEASED'; },
        'candidate' => static function (array &$metadata): void { $metadata['candidate'] = 'moving-head'; },
        'extra' => static function (array &$metadata, array &$resources): void { $resources['fallback'] = ['localhost']; },
        'missing-db' => static function (array &$metadata, array &$resources): void { array_pop($resources['mysql-db']); },
        'tree' => static function (array &$metadata, array &$resources): void { $resources['candidate-tree'] = ['tree']; },
        'proof-self' => static function (array &$metadata, array &$resources): void { $resources['lease-proof-dir'] = ['/tmp/other']; },
        'worktree' => static function (array &$metadata, array &$resources): void { $resources['worktree'] = ['/tmp/other']; },
        'endpoint' => static function (array &$metadata, array &$resources): void { $resources['endpoint'] = ['127.0.0.1:3306']; },
    ];
    foreach ($proofMutations as $case => $mutate) {
        $proof = $temporary . '/' . $case;
        resourceGuardWriteProof($proof, $guardRunId, $guardNow, $mutate);
        resourceGuardSetEnvironment(resourceGuardP0eEnvironment($guardRunId, 'standalone_fresh', 'standalone'));
        resourceGuardMustFail(static fn(): array => guardedDatabaseConfig($proof, $guardNow), $case);
    }

    $tampered = $temporary . '/tampered';
    resourceGuardWriteProof($tampered, $guardRunId, $guardNow);
    $bytes = (string)file_get_contents($tampered . '/resources.tsv');
    $bytes[0] = $bytes[0] === '0' ? '1' : '0';
    file_put_contents($tampered . '/resources.tsv', $bytes);
    resourceGuardSetEnvironment(resourceGuardP0eEnvironment($guardRunId, 'standalone_fresh', 'standalone'));
    resourceGuardMustFail(static fn(): array => guardedDatabaseConfig($tampered, $guardNow), 'resource hash');

    $configRejections = [
        'persistent-db' => ['DB_NAME' => 'peanut_admin_development'],
        'unknown-scenario' => ['DB_NAME' => 'peanut_admin_development_p0e_run123_unknown'],
        'foreign-run' => ['DB_NAME' => 'peanut_admin_development_p0e_other1_standalone_fresh'],
        'wrong-mode' => ['DB_NAME' => 'peanut_admin_development_p0e_run123_multi_tenant_fresh'],
        'host-consumer' => ['PEANUT_DATABASE_CONSUMER' => 'host'],
        'fallback-address' => ['DB_HOST' => '127.0.0.1', 'DB_PORT' => '3306'],
        'production-target' => ['PEANUT_DEPLOYMENT_TARGET' => 'production'],
    ];
    foreach ($configRejections as $case => $overrides) {
        resourceGuardSetEnvironment(resourceGuardP0eEnvironment($guardRunId, 'standalone_fresh', 'standalone', $overrides));
        resourceGuardMustFail(static fn(): array => guardedDatabaseConfig($activeProof, $guardNow), $case);
    }
    resourceGuardSetEnvironment(resourceGuardP0eEnvironment($guardRunId, 'standalone_fresh', 'standalone'));
    resourceGuardMustFail(
        static fn(): array => guardedDatabaseConfig($temporary . '/released-and-deleted', $guardNow),
        'deleted proof directory'
    );
} finally {
    resourceGuardDelete($temporary);
}

echo "database resource registry contract passed\n";
