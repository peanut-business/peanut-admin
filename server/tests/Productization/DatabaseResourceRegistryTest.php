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
