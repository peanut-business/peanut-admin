<?php
declare(strict_types=1);

$root = dirname(__DIR__, 3);
$registryPath = $root . '/resources/project-resources.json';
$registryJson = (string)file_get_contents($registryPath);
$registry = json_decode($registryJson, true, 512, JSON_THROW_ON_ERROR);

$expect = static function (bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
};

$expect(($registry['schema_version'] ?? null) === 1, 'resource registry schema version is invalid');
$expect(($registry['project_id'] ?? null) === 'peanut-admin', 'resource registry project id is invalid');

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
    'purpose', 'owner', 'allocation_resource_id', 'service_type', 'schema', 'allowed_scenarios',
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
$guard = (string)file_get_contents($root . '/server/database/environment-guard.php');
$devCompose = (string)file_get_contents($root . '/deploy/docker-compose.dev.yml');
$hostRuntime = (string)file_get_contents($root . '/scripts/local-php-runtime');

$expect(str_contains($rootInstructions, 'resources/project-resources.json'), 'root AGENTS.md does not reference the registry');
$expect(str_contains($localStack, 'project-resource-registry'), 'local stack does not consume the registry');
$expect(str_contains($probe, 'resources/project-resources.json'), 'probe does not consume the registry');
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

echo "database resource registry contract passed\n";
