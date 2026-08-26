<?php
declare(strict_types=1);

namespace app\platform\service\plugin;

use PDO;

/** Promotes one verified package overlay, rebuilds the canonical lock, then invokes the shared lifecycle. */
final class PluginPackageInstaller
{
    /**
     * @param array<string,mixed> $moduleConfig
     * @param array<string,string> $trustedPublicKeys key_id => raw Ed25519 public key
     */
    public function __construct(
        private readonly PDO $pdo,
        private readonly string $serverRoot,
        private readonly array $moduleConfig,
        private readonly array $trustedPublicKeys,
    ) {}

    /** @return array<string,mixed> */
    public function install(
        string $archivePath,
        ?string $expectedSha256,
        ?string $signatureKeyId,
    ): array {
        $archive = new PluginPackageArchiveService($this->serverRoot);
        $current = $this->currentDescriptors();
        $availableVersions = $this->moduleVersions($current);
        $package = $archive->verify(
            $archivePath,
            $expectedSha256,
            $this->trustedPublicKeys,
            $signatureKeyId,
            $availableVersions,
        );
        $promoted = [];
        $lockPath = $this->projectRoot() . '/plugins.lock';
        $lockBefore = is_file($lockPath) ? file_get_contents($lockPath) : null;
        $lifecycleStarted = false;
        try {
            foreach ($current as $pluginKey => $descriptor) {
                foreach (array_keys($descriptor->moduleRoots) as $moduleKey) {
                    if (isset($package->modules[$moduleKey]) && $pluginKey !== $package->packageKey) {
                        throw new PluginPackageException('PLUGIN_MODULE_CONFLICT', 'Module is owned by another package.');
                    }
                }
            }
            $scopes = [$package->manifestRelative => dirname($package->manifestRelative)];
            foreach ($package->modules as $module) {
                $scopes[$module['backend_relative']] = $module['backend_relative'];
                if ($module['frontend_relative'] !== null) {
                    $scopes[$module['frontend_relative']] = $module['frontend_relative'];
                }
            }
            $scopeRoots = array_values(array_unique(array_map(
                static fn(string $scope): string => str_ends_with($scope, 'plugin.json') ? dirname($scope) : $scope,
                array_keys($scopes),
            )));
            usort($scopeRoots, static fn(string $left, string $right): int => strlen($right) <=> strlen($left));
            foreach ($scopeRoots as $relative) {
                $source = $package->stageRoot . '/' . $relative;
                $target = $this->projectRoot() . '/' . $relative;
                if (file_exists($target)) {
                    if (!$this->scopeMatches($relative, $target, $package->inventory)) {
                        throw new PluginPackageException('MODULE_PACKAGE_TARGET_CONFLICT', 'Package target contains a different identity.');
                    }
                    $this->removeTree($source);
                    continue;
                }
                $parent = dirname($target);
                if (!is_dir($parent) && !mkdir($parent, 0775, true) && !is_dir($parent)) {
                    throw new PluginPackageException('MODULE_PACKAGE_PROMOTION_FAILED', 'Package target parent is unavailable.');
                }
                if (!rename($source, $target)) {
                    throw new PluginPackageException('MODULE_PACKAGE_PROMOTION_FAILED', 'Package target promotion failed.');
                }
                $promoted[] = $target;
            }

            (new PluginArtifactWriter($this->serverRoot))->writeLock();
            $resolver = new PluginLockResolver($this->serverRoot, '../plugins.lock');
            $lifecycle = new PluginLifecycleService(
                $this->pdo,
                $resolver,
                new PluginModuleRegistryFactory($this->pdo, $this->serverRoot),
                $this->moduleConfig,
            );
            $lifecycleStarted = true;
            $result = $lifecycle->install($package->packageKey);
            return $result + [
                'archive_sha256' => $package->archiveSha256,
                'package_key' => $package->packageKey,
                'modules' => array_map(
                    static fn(array $module): array => [
                        'module_key' => $module['key'],
                        'version' => $module['version'],
                        'status' => 'active',
                    ],
                    array_values($package->modules),
                ),
            ];
        } catch (\Throwable $exception) {
            if (!$lifecycleStarted) {
                if (is_string($lockBefore)) {
                    $this->writeAtomic($lockPath, $lockBefore);
                } elseif (is_file($lockPath)) {
                    unlink($lockPath);
                }
                foreach (array_reverse($promoted) as $target) {
                    $this->removeTree($target);
                }
            }
            throw $exception;
        } finally {
            $archive->cleanup($package);
        }
    }

    /** @return array<string,PluginDescriptor> */
    private function currentDescriptors(): array
    {
        $lockPath = $this->projectRoot() . '/plugins.lock';
        if (!is_file($lockPath)) {
            return [];
        }
        return (new PluginLockResolver($this->serverRoot, '../plugins.lock'))->all();
    }

    /** @param array<string,PluginDescriptor> $descriptors @return array<string,string> */
    private function moduleVersions(array $descriptors): array
    {
        $versions = [];
        foreach ($descriptors as $descriptor) {
            foreach ($descriptor->moduleRoots as $moduleKey => $root) {
                try {
                    $manifest = json_decode((string)file_get_contents($root . '/module.json'), true, 32, JSON_THROW_ON_ERROR);
                } catch (\JsonException) {
                    continue;
                }
                if (is_array($manifest) && is_string($manifest['version'] ?? null)) {
                    $versions[$moduleKey] = $manifest['version'];
                }
            }
        }
        return $versions;
    }

    /** @param array<string,string> $inventory */
    private function scopeMatches(string $relativeRoot, string $targetRoot, array $inventory): bool
    {
        $expected = [];
        $prefix = rtrim($relativeRoot, '/') . '/';
        foreach ($inventory as $path => $digest) {
            if (str_starts_with($path, $prefix)) {
                $expected[$path] = $digest;
            }
        }
        if ($expected === []) {
            return false;
        }
        $actual = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($targetRoot, \FilesystemIterator::SKIP_DOTS),
        );
        foreach ($iterator as $file) {
            if ($file->isLink() || !$file->isFile()) {
                return false;
            }
            $relative = ltrim(substr($file->getPathname(), strlen($this->projectRoot())), '/');
            $digest = hash_file('sha256', $file->getPathname());
            if (!is_string($digest)) {
                return false;
            }
            $actual[$relative] = $digest;
        }
        ksort($expected, SORT_STRING);
        ksort($actual, SORT_STRING);
        return $actual === $expected;
    }

    private function writeAtomic(string $path, string $contents): void
    {
        $temporary = $path . '.tmp-' . bin2hex(random_bytes(8));
        if (file_put_contents($temporary, $contents, LOCK_EX) === false || !rename($temporary, $path)) {
            if (is_file($temporary)) {
                unlink($temporary);
            }
            throw new PluginPackageException('MODULE_PACKAGE_RECOVERY_FAILED', 'Package lock recovery failed.');
        }
    }

    private function removeTree(string $path): void
    {
        if (!is_dir($path)) {
            if (is_file($path)) {
                unlink($path);
            }
            return;
        }
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );
        foreach ($iterator as $entry) {
            $entry->isDir() ? rmdir($entry->getPathname()) : unlink($entry->getPathname());
        }
        rmdir($path);
    }

    private function projectRoot(): string
    {
        return dirname($this->serverRoot);
    }
}
