<?php
declare(strict_types=1);

use app\platform\service\plugin\DevelopmentModuleDiscovery;
use PeanutAdmin\Kernel\Module\ModuleHostLayout;
use PeanutAdmin\Kernel\Module\ModuleKey;

require dirname(__DIR__, 2) . '/vendor/autoload.php';

function moduleCreateExpect(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}

/** @param list<string> $arguments @return array{code:int,output:string,json:array<string,mixed>} */
function moduleCreateRun(string $serverRoot, array $arguments): array
{
    $pipes = [];
    $process = proc_open(
        [PHP_BINARY, $serverRoot . '/think', 'module:create', ...$arguments],
        [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
        $pipes,
        $serverRoot,
    );
    if (!is_resource($process)) throw new RuntimeException('module:create process could not start');
    $stdout = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    $code = proc_close($process);
    $output = trim((string)$stdout . "\n" . (string)$stderr);
    $lines = array_values(array_filter(array_map('trim', explode("\n", $output)), 'strlen'));
    $json = json_decode((string)($lines[array_key_last($lines)] ?? ''), true);
    return ['code' => $code, 'output' => $output, 'json' => is_array($json) ? $json : []];
}

function moduleCreateTreeDigest(string $root): string
{
    $files = [];
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
    );
    foreach ($iterator as $entry) {
        if (!$entry->isFile() || $entry->isLink()) continue;
        $relative = substr($entry->getPathname(), strlen($root) + 1);
        $files[$relative] = hash_file('sha256', $entry->getPathname());
    }
    ksort($files, SORT_STRING);
    return hash('sha256', json_encode($files, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
}

function moduleCreateRemoveTree(string $path): void
{
    if (!is_dir($path) || is_link($path)) return;
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST,
    );
    foreach ($iterator as $entry) {
        $entry->isDir() && !$entry->isLink() ? rmdir($entry->getPathname()) : unlink($entry->getPathname());
    }
    rmdir($path);
}

/** @return array{code:int,output:string} */
function moduleCreateRunViteDiscovery(string $projectRoot): array
{
    $pipes = [];
    $process = proc_open(
        ['node', $projectRoot . '/web/tests/Productization/module-dev-discovery.test.mjs'],
        [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
        $pipes,
        $projectRoot . '/web',
    );
    if (!is_resource($process)) throw new RuntimeException('Vite discovery process could not start');
    $stdout = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    return ['code' => proc_close($process), 'output' => trim((string)$stdout . "\n" . (string)$stderr)];
}

$serverRoot = dirname(__DIR__, 2);
$projectRoot = dirname($serverRoot);
$layout = new ModuleHostLayout('server/app/Modules', 'app\\Modules', 'web/src/modules');
$suffix = bin2hex(random_bytes(6));
$officialKey = 'official.generated-' . $suffix;
$customKey = 'acme.generated-' . $suffix;
$officialModuleKey = ModuleKey::fromString($officialKey);
$customModuleKey = ModuleKey::fromString($customKey);
$officialBackend = $projectRoot . '/' . rtrim($layout->backendRelativePath($officialModuleKey), '/');
$officialFrontend = $projectRoot . '/' . rtrim($layout->frontendRelativePath($officialModuleKey), '/');
$customBackend = $projectRoot . '/' . rtrim($layout->backendRelativePath($customModuleKey), '/');
$customFrontend = $projectRoot . '/' . rtrim($layout->frontendRelativePath($customModuleKey), '/');
$customVendorRoot = $projectRoot . '/server/app/Modules/Acme';
$customVendorRootExisted = is_dir($customVendorRoot);

try {
    $official = moduleCreateRun($serverRoot, [$officialKey]);
    moduleCreateExpect($official['code'] === 0, 'default Official Module creation failed: ' . $official['output']);
    moduleCreateExpect(($official['json']['module_key'] ?? null) === $officialKey, 'Official creation returned another key');
    moduleCreateExpect(($official['json']['vendor'] ?? null) === 'Official', 'Official vendor was not derived');

    $custom = moduleCreateRun($serverRoot, [$customKey, '--vendor=Acme']);
    moduleCreateExpect($custom['code'] === 0, 'custom vendor Module creation failed: ' . $custom['output']);
    moduleCreateExpect(($custom['json']['module_key'] ?? null) === $customKey, 'custom creation returned another key');
    moduleCreateExpect(($custom['json']['vendor'] ?? null) === 'Acme', 'custom vendor changed');

    $expectedBackendFiles = [
        'module.json', 'ModuleProvider.php', 'Http/routes.php', 'Http/Controller/.gitkeep',
        'Service/.gitkeep', 'Model/.gitkeep', 'Resources/permissions.json', 'Resources/menus.json',
        'Resources/setting-definitions.json', 'Database/Migrations/.gitkeep', 'composer.json',
    ];
    $expectedFrontendFiles = ['contribution.ts', 'views/.gitkeep', 'api.ts', 'package.json'];
    foreach ([$officialBackend, $customBackend] as $root) {
        foreach ($expectedBackendFiles as $relative) {
            moduleCreateExpect(is_file($root . '/' . $relative), "generated backend file is missing: {$relative}");
        }
    }
    foreach ([$officialFrontend, $customFrontend] as $root) {
        foreach ($expectedFrontendFiles as $relative) {
            moduleCreateExpect(is_file($root . '/' . $relative), "generated frontend file is missing: {$relative}");
        }
    }

    $officialManifest = json_decode((string)file_get_contents($officialBackend . '/module.json'), true, 64, JSON_THROW_ON_ERROR);
    moduleCreateExpect(($officialManifest['frontend']['entry'] ?? null) === 'web/src/modules/' . $officialModuleKey->slug() . '/contribution.ts', 'frontend.entry is not key-derived');
    moduleCreateExpect(($officialManifest['backend']['migrations'] ?? null) === 'Database/Migrations', 'generated migrations declaration is missing');
    moduleCreateExpect(($officialManifest['backend']['setting_definitions'] ?? null) === 'Resources/setting-definitions.json', 'generated setting definitions declaration is missing');
    moduleCreateExpect(($officialManifest['lifecycle']['protected'] ?? null) === false, 'generated Module must be removable by default');
    $customComposer = json_decode((string)file_get_contents($customBackend . '/composer.json'), true, 32, JSON_THROW_ON_ERROR);
    moduleCreateExpect(isset($customComposer['autoload']['psr-4']['app\\Modules\\Acme\\Generated' . ucfirst($suffix) . '\\']), 'custom Composer namespace is not key-derived');
    moduleCreateExpect(!str_contains((string)file_get_contents($customBackend . '/ModuleProvider.php'), '${'), 'backend template placeholder remains');
    moduleCreateExpect(!str_contains((string)file_get_contents($customFrontend . '/contribution.ts'), '${'), 'frontend template placeholder remains');

    $roots = (new DevelopmentModuleDiscovery($projectRoot))->moduleRoots();
    moduleCreateExpect(($roots[$officialKey] ?? null) === realpath($officialBackend), 'generated Official Module was not discovered');
    moduleCreateExpect(($roots[$customKey] ?? null) === realpath($customBackend), 'generated custom Module was not discovered');

    $vite = moduleCreateRunViteDiscovery($projectRoot);
    moduleCreateExpect($vite['code'] === 0, 'Vite dev did not discover generated contributions: ' . $vite['output']);

    $beforeDuplicate = moduleCreateTreeDigest($officialBackend) . moduleCreateTreeDigest($officialFrontend);
    $duplicate = moduleCreateRun($serverRoot, [$officialKey]);
    $afterDuplicate = moduleCreateTreeDigest($officialBackend) . moduleCreateTreeDigest($officialFrontend);
    moduleCreateExpect($duplicate['code'] === 1, 'duplicate Module creation was accepted');
    moduleCreateExpect(($duplicate['json']['error'] ?? null) === 'MODULE_CREATE_TARGET_EXISTS', 'duplicate failure code changed');
    moduleCreateExpect($beforeDuplicate === $afterDuplicate, 'duplicate creation changed the existing Module');

    $invalid = moduleCreateRun($serverRoot, ['Invalid/../key']);
    moduleCreateExpect($invalid['code'] === 1, 'invalid Module key was accepted');
    moduleCreateExpect(($invalid['json']['error'] ?? null) === 'MODULE_CREATE_KEY_INVALID', 'invalid key failure code changed');

    echo "MODULE-CREATE-001 passed official={$officialKey} custom={$customKey}\n";
} finally {
    moduleCreateRemoveTree($officialBackend);
    moduleCreateRemoveTree($officialFrontend);
    moduleCreateRemoveTree($customBackend);
    moduleCreateRemoveTree($customFrontend);
    if (!$customVendorRootExisted && is_dir($customVendorRoot) && (scandir($customVendorRoot) ?: []) === ['.', '..']) {
        rmdir($customVendorRoot);
    }
}
