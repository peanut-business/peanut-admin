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
        $replaced = [];
        $recoveryRoot = null;
        $lockPath = $this->projectRoot() . '/plugins.lock';
        $lockBefore = is_file($lockPath) ? file_get_contents($lockPath) : null;
        $lifecycleStarted = false;
        $lockName = 'pa:module-runtime:' . substr(hash('sha256', $package->packageKey), 0, 40);
        if (!$this->advisoryLock($lockName)) {
            $archive->cleanup($package);
            throw new PluginLifecycleException('MODULE_LIFECYCLE_BUSY', 'Module lifecycle is busy.');
        }
        try {
            foreach ($current as $pluginKey => $descriptor) {
                foreach (array_keys($descriptor->moduleRoots) as $moduleKey) {
                    if (isset($package->modules[$moduleKey]) && $pluginKey !== $package->packageKey) {
                        throw new PluginPackageException('PLUGIN_MODULE_CONFLICT', 'Module is owned by another package.');
                    }
                }
            }
            $recoverableReplacement = $this->recoverableReplacement($package, $current);
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
                        if (!$recoverableReplacement) {
                            throw new PluginPackageException('MODULE_PACKAGE_TARGET_CONFLICT', 'Package target contains a different identity.');
                        }
                        $recoveryRoot ??= $this->recoveryRoot($package->packageKey);
                        $backup = $recoveryRoot . '/' . $relative;
                        if (!is_dir(dirname($backup)) && !mkdir(dirname($backup), 0700, true) && !is_dir(dirname($backup))) {
                            throw new PluginPackageException('MODULE_PACKAGE_RECOVERY_FAILED', 'Package recovery backup cannot be created.');
                        }
                        if (!rename($target, $backup)) {
                            throw new PluginPackageException('MODULE_PACKAGE_RECOVERY_FAILED', 'Package recovery backup cannot be promoted.');
                        }
                        $replaced[$target] = $backup;
                    } else {
                        $this->removeTree($source);
                        continue;
                    }
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
            $result = $lifecycle->install($package->packageKey, false);
            $this->clearQuarantine($package->packageKey);
            if (is_string($recoveryRoot)) $this->removeTree($recoveryRoot);
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
                foreach (array_reverse($replaced, true) as $target => $backup) {
                    if (file_exists($backup) && !rename($backup, $target)) {
                        throw new PluginPackageException('MODULE_PACKAGE_RECOVERY_FAILED', 'Package recovery restore failed.', 0, $exception);
                    }
                }
            }
            if (is_string($recoveryRoot) && is_dir($recoveryRoot)) $this->removeTree($recoveryRoot);
            throw $exception;
        } finally {
            $this->releaseAdvisoryLock($lockName);
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

    /** @param array<string,PluginDescriptor> $current */
    private function recoverableReplacement(VerifiedPluginPackage $package, array $current): bool
    {
        $descriptor = $current[$package->packageKey] ?? null;
        if (!$descriptor instanceof PluginDescriptor) return false;
        $statement = $this->pdo->prepare('SELECT installed_version,status FROM pa_plugin_installation WHERE plugin_key=?');
        $statement->execute([$package->packageKey]);
        $installation = $statement->fetch(PDO::FETCH_ASSOC);
        if (!is_array($installation)
            || !in_array($installation['status'] ?? null, ['failed', 'installing'], true)
            || version_compare($package->packageVersion, (string)$installation['installed_version'], '<=')) {
            return false;
        }
        $currentModules = array_keys($descriptor->moduleRoots);
        $replacementModules = array_keys($package->modules);
        sort($currentModules, SORT_STRING);
        sort($replacementModules, SORT_STRING);
        return $currentModules === $replacementModules;
    }

    private function recoveryRoot(string $packageKey): string
    {
        $root = $this->projectRoot() . '/.local/module-install-recovery/' . $packageKey . '-' . bin2hex(random_bytes(12));
        if (!mkdir($root, 0700, true) && !is_dir($root)) {
            throw new PluginPackageException('MODULE_PACKAGE_RECOVERY_FAILED', 'Package recovery root cannot be created.');
        }
        return $root;
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

    private function clearQuarantine(string $packageKey): void
    {
        if (preg_match('/^[a-z][a-z0-9]*(?:[.-][a-z0-9]+)*$/D', $packageKey) !== 1) {
            throw new PluginPackageException('MODULE_QUARANTINE_INVALID', 'Module package key is invalid.');
        }
        $root = $this->projectRoot() . '/.local/module-quarantine';
        if (!is_dir($root)) return;
        $resolvedRoot = realpath($root);
        if (!is_string($resolvedRoot)) {
            throw new PluginPackageException('MODULE_QUARANTINE_INVALID', 'Module quarantine root is invalid.');
        }
        foreach (glob($root . '/' . $packageKey . '-*', GLOB_ONLYDIR) ?: [] as $candidate) {
            $resolved = realpath($candidate);
            if (is_link($candidate) || !is_string($resolved)
                || !str_starts_with($resolved, $resolvedRoot . DIRECTORY_SEPARATOR)) {
                throw new PluginPackageException('MODULE_QUARANTINE_INVALID', 'Module quarantine path is invalid.');
            }
            $this->removeTree($resolved);
            if (file_exists($resolved)) {
                throw new PluginPackageException('MODULE_QUARANTINE_FAILED', 'Module quarantine cannot be cleared after install.');
            }
        }
    }

    private function advisoryLock(string $name): bool
    {
        $statement = $this->pdo->prepare('SELECT GET_LOCK(?,0)');
        $statement->execute([$name]);
        return (int)$statement->fetchColumn() === 1;
    }

    private function releaseAdvisoryLock(string $name): void
    {
        try {
            $statement = $this->pdo->prepare('SELECT RELEASE_LOCK(?)');
            $statement->execute([$name]);
        } catch (\Throwable) {
        }
    }

    private function projectRoot(): string
    {
        return dirname($this->serverRoot);
    }
}
