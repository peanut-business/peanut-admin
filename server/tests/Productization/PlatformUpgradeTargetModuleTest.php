<?php
declare(strict_types=1);

use app\platform\service\ops\PlatformUpgradeReadinessService;
use app\platform\service\ops\PlatformOpsRuntimeFactory;
use app\platform\service\ops\PlatformUpgradeTarget;
use app\platform\service\plugin\PluginLockResolver;
use PeanutAdmin\Kernel\Module\ManifestLoader;
use think\Config as ThinkConfig;
use think\Container;
use think\facade\Config;

require dirname(__DIR__, 2) . '/vendor/autoload.php';
require_once dirname(__DIR__, 2) . '/app/platform/service/plugin/PluginLifecycleException.php';
require_once dirname(__DIR__, 2) . '/app/platform/service/plugin/PluginDescriptor.php';
require_once dirname(__DIR__, 2) . '/app/platform/service/plugin/PluginLockResolver.php';
require_once dirname(__DIR__, 2) . '/app/platform/service/ops/PlatformUpgradeTarget.php';
require_once dirname(__DIR__, 2) . '/app/platform/service/ops/PlatformUpgradeReadinessService.php';

function upgradeTargetExpect(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function upgradeTargetCopyTree(string $source, string $target): void
{
    if (!is_dir($target) && !mkdir($target, 0700, true) && !is_dir($target)) {
        throw new RuntimeException('unable to create target fixture directory');
    }
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($source, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST,
    );
    foreach ($iterator as $entry) {
        $relative = substr($entry->getPathname(), strlen($source) + 1);
        $destination = $target . '/' . $relative;
        if ($entry->isDir()) {
            if (!is_dir($destination) && !mkdir($destination, 0700, true) && !is_dir($destination)) {
                throw new RuntimeException('unable to create target fixture directory');
            }
            continue;
        }
        if (!copy($entry->getPathname(), $destination)) {
            throw new RuntimeException('unable to copy target fixture file');
        }
    }
}

function upgradeTargetRemoveTree(string $path): void
{
    if (is_link($path) || is_file($path)) {
        unlink($path);
        return;
    }
    if (!is_dir($path)) {
        return;
    }
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST,
    );
    foreach ($iterator as $entry) {
        $entry->isDir() && !$entry->isLink()
            ? rmdir($entry->getPathname())
            : unlink($entry->getPathname());
    }
    rmdir($path);
}

/** @param array<string,mixed> $document */
function upgradeTargetWriteJson(string $path, array $document): void
{
    $directory = dirname($path);
    if (!is_dir($directory) && !mkdir($directory, 0700, true) && !is_dir($directory)) {
        throw new RuntimeException('unable to create JSON fixture directory');
    }
    file_put_contents(
        $path,
        json_encode($document, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n",
    );
}

/** @param list<string> $roots */
function upgradeTargetCanonicalDigest(string $projectRoot, array $roots): string
{
    $projectRoot = realpath($projectRoot) ?: $projectRoot;
    $files = [];
    foreach ($roots as $root) {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
        );
        foreach ($iterator as $file) {
            if (!$file->isFile() || $file->isLink()) {
                continue;
            }
            $path = $file->getRealPath() ?: $file->getPathname();
            $relative = str_replace('\\', '/', substr($path, strlen($projectRoot) + 1));
            $files[$relative] = hash_file('sha256', $file->getPathname());
        }
    }
    ksort($files, SORT_STRING);
    $canonical = '';
    foreach ($files as $relative => $digest) {
        $canonical .= $relative . "\0" . $digest . "\n";
    }
    return hash('sha256', $canonical);
}

/** @param callable():void $operation */
function upgradeTargetRejects(callable $operation, string $expected): void
{
    try {
        $operation();
    } catch (RuntimeException $exception) {
        upgradeTargetExpect($exception->getMessage() === $expected, 'unexpected target rejection code');
        return;
    }
    throw new RuntimeException("target fixture accepted invalid input: {$expected}");
}

$sourceRoot = dirname(__DIR__, 3);
$temporary = sys_get_temp_dir() . '/pa-upgrade-target-module-' . bin2hex(random_bytes(8));
$projectRoot = $temporary . '/application';
$targetRoot = $projectRoot . '/.peanut/upgrade-target';
$releaseRoot = $targetRoot . '/release';
$currentModule = $projectRoot . '/server/app/Modules/Fixture/DeliveryRecord';
$currentFrontend = $projectRoot . '/web/src/modules/fixture-delivery-record';
$targetModule = $releaseRoot . '/server/app/Modules/Fixture/DeliveryRecord';
$targetFrontend = $releaseRoot . '/web/src/modules/fixture-delivery-record';

mkdir($temporary, 0700, true);
try {
    foreach ([
        [$sourceRoot . '/server/app/Modules/Fixture/DeliveryRecord', $currentModule],
        [$sourceRoot . '/web/src/modules/fixture-delivery-record', $currentFrontend],
        [$sourceRoot . '/server/app/Modules/Fixture/DeliveryRecord', $targetModule],
        [$sourceRoot . '/web/src/modules/fixture-delivery-record', $targetFrontend],
    ] as [$source, $target]) {
        upgradeTargetCopyTree($source, $target);
    }
    upgradeTargetCopyTree(
        $sourceRoot . '/plugins/fixture.delivery-record',
        $releaseRoot . '/plugins/fixture.delivery-record',
    );

    $targetMarker = $targetModule . '/Application/target-release-proof.txt';
    file_put_contents($targetMarker, "target release module bytes\n");
    upgradeTargetExpect(!is_file($currentModule . '/Application/target-release-proof.txt'), 'target marker leaked into current source');

    $sourceLock = json_decode(
        (string)file_get_contents($sourceRoot . '/plugins.lock'),
        true,
        64,
        JSON_THROW_ON_ERROR,
    );
    $plugin = array_values(array_filter(
        $sourceLock['plugins'],
        static fn(array $entry): bool => ($entry['key'] ?? null) === 'fixture.delivery-record',
    ))[0] ?? null;
    upgradeTargetExpect(is_array($plugin), 'fixture Plugin lock entry is unavailable');

    $plugin['source']['sha256'] = upgradeTargetCanonicalDigest(
        $releaseRoot,
        [$targetModule, $targetFrontend],
    );
    $plugin['trust']['compatibility']['modules'][0]['kernel_constraint'] = '^2.0';
    $pluginManifestPath = $releaseRoot . '/plugins/fixture.delivery-record/plugin.json';
    $pluginManifest = json_decode((string)file_get_contents($pluginManifestPath), true, 64, JSON_THROW_ON_ERROR);
    $pluginManifest['source'] = $plugin['source'];
    $pluginManifest['trust'] = $plugin['trust'];
    upgradeTargetWriteJson($pluginManifestPath, $pluginManifest);
    $plugin['manifest_sha256'] = hash_file('sha256', $pluginManifestPath);
    $targetLockPath = $releaseRoot . '/plugins.lock';
    upgradeTargetWriteJson($targetLockPath, ['schema_version' => 1, 'plugins' => [$plugin]]);

    $sourceCommit = str_repeat('a', 40);
    $sourceTree = str_repeat('b', 40);
    $inventoryDigest = str_repeat('c', 64);
    $fromManifest = [
        'release' => [
            'version' => '3.0.8',
            'source_commit' => str_repeat('d', 40),
            'source_tree' => str_repeat('e', 40),
            'inventory_sha256' => str_repeat('f', 64),
        ],
    ];
    $toManifest = [
        'release' => [
            'version' => '3.0.9',
            'source_commit' => $sourceCommit,
            'source_tree' => $sourceTree,
            'inventory_sha256' => $inventoryDigest,
        ],
    ];
    $fromManifestPath = $targetRoot . '/from/scaffold-manifest.json';
    $toManifestPath = $targetRoot . '/to/scaffold-manifest.json';
    upgradeTargetWriteJson($fromManifestPath, $fromManifest);
    upgradeTargetWriteJson($toManifestPath, $toManifest);
    $emptyMigrationDigest = hash('sha256', '[]');
    $descriptor = [
        'schema_version' => 1,
        'protocol' => 'peanut.application-upgrade-target.v1',
        'release' => [
            'key' => 'v3.0.9',
            'commit' => $sourceCommit,
            'tree' => $sourceTree,
            'qualification' => [
                'status' => 'passed',
                'candidate_commit' => $sourceCommit,
                'candidate_tree' => $sourceTree,
                'groups_passed' => 7,
                'cleanup_residual_count' => 0,
                'lease_released' => true,
            ],
        ],
        'scaffold' => [
            'from_version' => '3.0.8',
            'from_manifest_sha256' => hash_file('sha256', $fromManifestPath),
            'to_version' => '3.0.9',
            'to_manifest_sha256' => hash_file('sha256', $toManifestPath),
        ],
        'migrations' => [
            'from' => ['inventory_sha256' => $emptyMigrationDigest, 'files' => []],
            'to' => ['inventory_sha256' => $emptyMigrationDigest, 'files' => []],
        ],
        'modules' => [
            'lock_sha256' => hash_file('sha256', $targetLockPath),
            'kernel_version' => '2.0.0',
        ],
    ];
    $descriptorPath = $targetRoot . '/target.json';
    upgradeTargetWriteJson($descriptorPath, $descriptor);

    $target = PlatformUpgradeTarget::load($projectRoot);
    upgradeTargetExpect($target->releaseRoot === realpath($releaseRoot), 'target release root changed');
    upgradeTargetExpect($target->releaseServerRoot === realpath($releaseRoot . '/server'), 'target server root changed');
    upgradeTargetExpect($target->targetLockPath === realpath($targetLockPath), 'target lock path changed');
    $resolved = (new PluginLockResolver($target->releaseServerRoot, $target->targetLockPath))
        ->require('fixture.delivery-record');
    upgradeTargetExpect(
        $resolved->moduleRoots['fixture.delivery-record'] === realpath($targetModule),
        'target lock resolved Module bytes from the current application',
    );

    $container = new Container();
    Container::setInstance($container);
    $container->instance('config', new ThinkConfig());
    Config::set([
        'roots' => ['app/Modules/Fixture/DeliveryRecord'],
        'kernel_version' => '1.0.0',
        'registered_client_keys' => ['admin-web', 'platform-web'],
    ], 'modules');

    $pdo = new PDO('sqlite::memory:', null, null, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    $pdo->exec(<<<'SQL'
CREATE TABLE pa_module_installation (
  module_key TEXT PRIMARY KEY,
  installed_version TEXT NOT NULL,
  manifest_schema_version INTEGER NOT NULL,
  manifest_digest TEXT NOT NULL,
  status TEXT NOT NULL
);
CREATE TABLE pa_plugin_module (
  plugin_key TEXT NOT NULL,
  module_key TEXT NOT NULL
);
SQL);
    $currentManifest = (new ManifestLoader())->load(
        $sourceRoot . '/server/app/Modules/Fixture/DeliveryRecord',
    );
    $statement = $pdo->prepare(
        'INSERT INTO pa_module_installation '
        . '(module_key,installed_version,manifest_schema_version,manifest_digest,status) VALUES (?,?,?,?,?)',
    );
    $statement->execute([
        'fixture.delivery-record',
        '1.0.0',
        1,
        $currentManifest->digest,
        'active',
    ]);

    $moduleConfig = Config::get('modules', []);
    upgradeTargetExpect(is_array($moduleConfig), 'Module fixture configuration is unavailable');
    $service = (new PlatformOpsRuntimeFactory($pdo, $projectRoot, $moduleConfig, []))->readiness();
    $moduleProjection = Closure::bind(
        fn(PlatformUpgradeTarget $value): array => $this->moduleProjection($value),
        $service,
        PlatformUpgradeReadinessService::class,
    );
    upgradeTargetExpect(is_callable($moduleProjection), 'Module projection fixture cannot access the focused method');
    $projection = $moduleProjection($target);
    upgradeTargetExpect(
        ($projection['status'] ?? null) === 'ready'
            && ($projection['compatible_count'] ?? null) === 1
            && ($projection['target_kernel_version'] ?? null) === '2.0.0',
        'target Module source or target Kernel constraint was not used',
    );

    $invalidKernel = $descriptor;
    $invalidKernel['modules']['kernel_version'] = '^2.0';
    upgradeTargetWriteJson($descriptorPath, $invalidKernel);
    upgradeTargetRejects(
        static fn() => PlatformUpgradeTarget::load($projectRoot),
        'UPGRADE_TARGET_MODULE_LOCK_INVALID',
    );
    upgradeTargetWriteJson($descriptorPath, $descriptor);

    $outside = $temporary . '/outside.txt';
    file_put_contents($outside, "outside\n");
    $symlink = $releaseRoot . '/target-escape';
    symlink($outside, $symlink);
    upgradeTargetRejects(
        static fn() => PlatformUpgradeTarget::load($projectRoot),
        'UPGRADE_TARGET_RELEASE_TREE_INVALID',
    );
    unlink($symlink);
} finally {
    upgradeTargetRemoveTree($temporary);
}

echo "PLATFORM-UPGRADE-TARGET-MODULE-001 passed\n";
