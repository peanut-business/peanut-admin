<?php
declare(strict_types=1);

use app\platform\service\plugin\ModulePackagePreflight;
use app\platform\service\plugin\PluginPackageArchiveService;
use PeanutAdmin\Kernel\Module\ModuleKey;

require dirname(__DIR__, 2) . '/vendor/autoload.php';

function allModulesPackagingExpect(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$serverRoot = dirname(__DIR__, 2);
$projectRoot = dirname($serverRoot);
$resultsPath = $argv[1] ?? '/tmp/module-packages-test/packaging-results.json';
$expectedModules = [
    'official.article',
    'official.file',
    'official.task',
    'official.notification',
    'official.member',
    'official.payment',
    'official.oauth',
    'official.import-export',
];

allModulesPackagingExpect(is_file($resultsPath), "Packaging result evidence is missing: {$resultsPath}");
$evidence = json_decode((string)file_get_contents($resultsPath), true, 64, JSON_THROW_ON_ERROR);
allModulesPackagingExpect(is_array($evidence) && !array_is_list($evidence), 'Packaging result evidence root is invalid');
allModulesPackagingExpect(
    is_string($evidence['candidate'] ?? null)
        && preg_match('/^[a-f0-9]{40}$/D', $evidence['candidate']) === 1,
    'Packaging candidate identity is invalid',
);

$results = $evidence['results'] ?? null;
allModulesPackagingExpect(is_array($results) && array_is_list($results), 'Packaging results are invalid');
$resultsByModule = [];
foreach ($results as $result) {
    allModulesPackagingExpect(is_array($result), 'Packaging result row is invalid');
    $moduleKey = $result['module_key'] ?? null;
    allModulesPackagingExpect(is_string($moduleKey) && !isset($resultsByModule[$moduleKey]), 'Packaging result identity is invalid');
    $resultsByModule[$moduleKey] = $result;
}
allModulesPackagingExpect(array_keys($resultsByModule) === $expectedModules, 'Official Module packaging evidence is incomplete or reordered');

$availableVersions = [];
$sourcePreflight = new ModulePackagePreflight($projectRoot);
foreach ($expectedModules as $moduleKey) {
    $availableVersions[$moduleKey] = $sourcePreflight->inspect($moduleKey)['version'];
}

$archiveService = new PluginPackageArchiveService($serverRoot);
foreach ($expectedModules as $moduleKey) {
    $result = $resultsByModule[$moduleKey];
    allModulesPackagingExpect(($result['exit_code'] ?? null) === 0, "Module package failed: {$moduleKey}");
    allModulesPackagingExpect(($result['error'] ?? null) === null, "Module package recorded an error: {$moduleKey}");

    $archivePath = $result['tar_path'] ?? null;
    $expectedSize = $result['tar_size'] ?? null;
    $expectedSha256 = $result['sha256'] ?? null;
    allModulesPackagingExpect(is_string($archivePath) && is_file($archivePath), "Module package is missing: {$moduleKey}");
    allModulesPackagingExpect(is_int($expectedSize) && filesize($archivePath) === $expectedSize, "Module package size differs: {$moduleKey}");
    allModulesPackagingExpect(
        is_string($expectedSha256)
            && preg_match('/^[a-f0-9]{64}$/D', $expectedSha256) === 1
            && hash_equals($expectedSha256, (string)hash_file('sha256', $archivePath)),
        "Module package SHA-256 differs: {$moduleKey}",
    );

    $verified = $archiveService->verify($archivePath, $expectedSha256, [], null, $availableVersions);
    try {
        allModulesPackagingExpect($verified->packageKey === $moduleKey, "Plugin identity differs: {$moduleKey}");
        allModulesPackagingExpect($verified->manifestRelative === "plugins/{$moduleKey}/plugin.json", "Plugin manifest path differs: {$moduleKey}");
        allModulesPackagingExpect(isset($verified->inventory[$verified->manifestRelative]), "Plugin manifest is absent from the verified inventory: {$moduleKey}");
        allModulesPackagingExpect(array_keys($verified->modules) === [$moduleKey], "Single-Module package has another Module identity: {$moduleKey}");

        $module = $verified->modules[$moduleKey];
        $manifest = $module['manifest']->data;
        foreach (($manifest['catalog']['permissions'] ?? []) as $permission) {
            $permissionKey = is_array($permission) ? ($permission['key'] ?? null) : null;
            allModulesPackagingExpect(
                is_string($permissionKey) && str_starts_with($permissionKey, $moduleKey . '.'),
                "Permission escaped the Module namespace: {$moduleKey}",
            );
        }

        $frontendEntry = $manifest['frontend']['entry'] ?? null;
        if ($frontendEntry !== null) {
            $derivedEntry = 'web/src/modules/' . ModuleKey::fromString($moduleKey)->slug() . '/contribution.ts';
            allModulesPackagingExpect($frontendEntry === $derivedEntry, "frontend.entry is not key-derived: {$moduleKey}");
            allModulesPackagingExpect(isset($verified->inventory[$derivedEntry]), "Frontend entry is absent from the verified inventory: {$moduleKey}");
        }
    } finally {
        $archiveService->cleanup($verified);
    }
}

$productionBuild = $evidence['production_build'] ?? null;
allModulesPackagingExpect(is_array($productionBuild), 'Production build evidence is missing');
allModulesPackagingExpect(($productionBuild['exit_code'] ?? null) === 0, 'Production Web build failed');
allModulesPackagingExpect(($productionBuild['filename_dev_tools_hits'] ?? null) === [], 'Production bundle contains a dev-tools filename');
allModulesPackagingExpect(($productionBuild['symbol_hits'] ?? null) === [], 'Production bundle contains a dev-tools symbol');

echo 'ALL-MODULES-PACKAGING-001 passed modules=' . count($expectedModules)
    . ' candidate=' . $evidence['candidate'] . "\n";
