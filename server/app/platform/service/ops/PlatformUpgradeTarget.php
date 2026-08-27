<?php
declare(strict_types=1);

namespace app\platform\service\ops;

use RuntimeException;

/**
 * Validates the deployment-staged, fixed-path upgrade target bundle.
 *
 * The bundle is a privileged deployment input. HTTP callers cannot select a
 * path, URL, command, release key, or credential.
 */
final readonly class PlatformUpgradeTarget
{
    private const TARGET_DIRECTORY = '.peanut/upgrade-target';
    private const VERSION = '/^(0|[1-9][0-9]*)\.(0|[1-9][0-9]*)\.(0|[1-9][0-9]*)$/D';
    private const RELEASE_KEY = '/^v(0|[1-9][0-9]*)\.(0|[1-9][0-9]*)\.(0|[1-9][0-9]*)$/D';
    private const KERNEL_VERSION = '/^(0|[1-9][0-9]*)\.(0|[1-9][0-9]*)\.(0|[1-9][0-9]*)(?:[-+][0-9A-Za-z.-]+)?$/D';
    private const COMMIT = '/^[a-f0-9]{40}$/D';
    private const SHA256 = '/^[a-f0-9]{64}$/D';
    private const MIGRATION = '/^[0-9]{8}-[a-z0-9][a-z0-9_-]*$/D';

    /**
     * @param array{key:string,commit:string,tree:string,qualification:array<string,mixed>} $release
     * @param array{from_version:string,from_manifest_sha256:string,to_version:string,to_manifest_sha256:string} $scaffold
     * @param array{from:array{inventory_sha256:string,files:list<array{migration_id:string,sha256:string}>},to:array{inventory_sha256:string,files:list<array{migration_id:string,sha256:string}>}} $migrations
     * @param array{lock_sha256:string,kernel_version:string} $modules
     */
    private function __construct(
        public array $release,
        public array $scaffold,
        public array $migrations,
        public array $modules,
        public string $descriptorSha256,
        public string $fromManifestPath,
        public string $toManifestPath,
        public string $releaseRoot,
        public string $releaseServerRoot,
        public string $targetLockPath,
    ) {
    }

    public static function load(string $projectRoot): self
    {
        $root = self::targetRoot($projectRoot);
        $descriptorPath = self::fixedFile($root, 'target.json');
        $descriptor = self::json($descriptorPath, 'UPGRADE_TARGET_DESCRIPTOR_INVALID');
        self::exact($descriptor, ['schema_version', 'protocol', 'release', 'scaffold', 'migrations', 'modules']);
        if (($descriptor['schema_version'] ?? null) !== 1
            || ($descriptor['protocol'] ?? null) !== 'peanut.application-upgrade-target.v1') {
            throw new RuntimeException('UPGRADE_TARGET_DESCRIPTOR_INVALID');
        }

        $release = self::release($descriptor['release'] ?? null);
        $scaffold = self::scaffold($descriptor['scaffold'] ?? null);
        $migrations = self::migrations($descriptor['migrations'] ?? null);
        $modules = self::modules($descriptor['modules'] ?? null);
        if ($release['key'] !== 'v' . $scaffold['to_version']) {
            throw new RuntimeException('UPGRADE_TARGET_RELEASE_IDENTITY_INVALID');
        }

        $fromManifestPath = self::fixedFile($root, 'from/scaffold-manifest.json');
        $toManifestPath = self::fixedFile($root, 'to/scaffold-manifest.json');
        self::assertDigest($fromManifestPath, $scaffold['from_manifest_sha256']);
        self::assertDigest($toManifestPath, $scaffold['to_manifest_sha256']);
        self::assertScaffoldVersion($fromManifestPath, $scaffold['from_version']);
        $targetScaffoldRelease = self::assertScaffoldVersion(
            $toManifestPath,
            $scaffold['to_version']
        );
        if (!hash_equals($release['commit'], $targetScaffoldRelease['source_commit'])
            || !hash_equals($release['tree'], $targetScaffoldRelease['source_tree'])) {
            throw new RuntimeException('UPGRADE_TARGET_RELEASE_IDENTITY_INVALID');
        }

        $releaseRoot = self::fixedDirectory($root, 'release');
        self::assertRegularTree($releaseRoot);
        $releaseServerRoot = self::fixedDirectory($releaseRoot, 'server');
        $targetLockPath = self::fixedFile($releaseRoot, 'plugins.lock');
        self::assertDigest($targetLockPath, $modules['lock_sha256']);

        $descriptorDigest = hash_file('sha256', $descriptorPath);
        if (!is_string($descriptorDigest)) {
            throw new RuntimeException('UPGRADE_TARGET_DESCRIPTOR_INVALID');
        }

        return new self(
            $release,
            $scaffold,
            $migrations,
            $modules,
            $descriptorDigest,
            $fromManifestPath,
            $toManifestPath,
            $releaseRoot,
            $releaseServerRoot,
            $targetLockPath,
        );
    }

    /** @return array<string,string> */
    public function sourceMigrationMap(): array
    {
        return self::migrationMap($this->migrations['from']['files']);
    }

    /** @return array<string,string> */
    public function targetMigrationMap(): array
    {
        return self::migrationMap($this->migrations['to']['files']);
    }

    private static function targetRoot(string $projectRoot): string
    {
        $candidate = rtrim($projectRoot, DIRECTORY_SEPARATOR)
            . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, self::TARGET_DIRECTORY);
        if (is_link($candidate)) {
            throw new RuntimeException('UPGRADE_TARGET_DESCRIPTOR_INVALID');
        }
        if (!is_dir($candidate)) {
            throw new RuntimeException('UPGRADE_TARGET_NOT_STAGED');
        }
        $resolved = realpath($candidate);
        $project = realpath($projectRoot);
        if ($resolved === false || $project === false
            || !str_starts_with($resolved, $project . DIRECTORY_SEPARATOR)) {
            throw new RuntimeException('UPGRADE_TARGET_DESCRIPTOR_INVALID');
        }
        return $resolved;
    }

    private static function fixedFile(string $root, string $relative): string
    {
        $candidate = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);
        if (!is_file($candidate) || is_link($candidate)) {
            throw new RuntimeException('UPGRADE_TARGET_DESCRIPTOR_INVALID');
        }
        $resolved = realpath($candidate);
        if ($resolved === false || !str_starts_with($resolved, $root . DIRECTORY_SEPARATOR)) {
            throw new RuntimeException('UPGRADE_TARGET_DESCRIPTOR_INVALID');
        }
        return $resolved;
    }

    private static function fixedDirectory(string $root, string $relative): string
    {
        $candidate = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);
        if (!is_dir($candidate) || is_link($candidate)) {
            throw new RuntimeException('UPGRADE_TARGET_DESCRIPTOR_INVALID');
        }
        $resolved = realpath($candidate);
        if ($resolved === false || !str_starts_with($resolved, $root . DIRECTORY_SEPARATOR)) {
            throw new RuntimeException('UPGRADE_TARGET_DESCRIPTOR_INVALID');
        }
        return $resolved;
    }

    private static function assertRegularTree(string $root): void
    {
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST,
        );
        foreach ($iterator as $entry) {
            if ($entry->isLink() || (!$entry->isDir() && !$entry->isFile())) {
                throw new RuntimeException('UPGRADE_TARGET_RELEASE_TREE_INVALID');
            }
            $resolved = $entry->getRealPath();
            if ($resolved === false
                || ($resolved !== $root && !str_starts_with($resolved, $root . DIRECTORY_SEPARATOR))) {
                throw new RuntimeException('UPGRADE_TARGET_RELEASE_TREE_INVALID');
            }
        }
    }

    /** @return array<string,mixed> */
    private static function json(string $path, string $code): array
    {
        try {
            $decoded = json_decode((string)file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            throw new RuntimeException($code, 0, $exception);
        }
        if (!is_array($decoded) || array_is_list($decoded)) {
            throw new RuntimeException($code);
        }
        return $decoded;
    }

    /** @return array{key:string,commit:string,tree:string,qualification:array<string,mixed>} */
    private static function release(mixed $value): array
    {
        if (!is_array($value) || array_is_list($value)) {
            throw new RuntimeException('UPGRADE_TARGET_RELEASE_IDENTITY_INVALID');
        }
        self::exact($value, ['key', 'commit', 'tree', 'qualification']);
        $qualification = $value['qualification'] ?? null;
        if (!is_array($qualification) || array_is_list($qualification)) {
            throw new RuntimeException('UPGRADE_TARGET_RELEASE_IDENTITY_INVALID');
        }
        self::exact($qualification, [
            'status', 'candidate_commit', 'candidate_tree', 'groups_passed',
            'cleanup_residual_count', 'lease_released',
        ]);
        $key = $value['key'] ?? null;
        $commit = $value['commit'] ?? null;
        $tree = $value['tree'] ?? null;
        if (!is_string($key) || preg_match(self::RELEASE_KEY, $key) !== 1
            || !is_string($commit) || preg_match(self::COMMIT, $commit) !== 1
            || !is_string($tree) || preg_match(self::COMMIT, $tree) !== 1
            || ($qualification['status'] ?? null) !== 'passed'
            || ($qualification['candidate_commit'] ?? null) !== $commit
            || ($qualification['candidate_tree'] ?? null) !== $tree
            || !is_int($qualification['groups_passed'] ?? null)
            || $qualification['groups_passed'] < 7
            || ($qualification['cleanup_residual_count'] ?? null) !== 0
            || ($qualification['lease_released'] ?? null) !== true) {
            throw new RuntimeException('UPGRADE_TARGET_RELEASE_IDENTITY_INVALID');
        }
        return ['key' => $key, 'commit' => $commit, 'tree' => $tree, 'qualification' => $qualification];
    }

    /** @return array{from_version:string,from_manifest_sha256:string,to_version:string,to_manifest_sha256:string} */
    private static function scaffold(mixed $value): array
    {
        if (!is_array($value) || array_is_list($value)) {
            throw new RuntimeException('UPGRADE_TARGET_SCAFFOLD_INVALID');
        }
        self::exact($value, ['from_version', 'from_manifest_sha256', 'to_version', 'to_manifest_sha256']);
        foreach (['from_version', 'to_version'] as $key) {
            if (!is_string($value[$key] ?? null) || preg_match(self::VERSION, $value[$key]) !== 1) {
                throw new RuntimeException('UPGRADE_TARGET_SCAFFOLD_INVALID');
            }
        }
        foreach (['from_manifest_sha256', 'to_manifest_sha256'] as $key) {
            if (!is_string($value[$key] ?? null) || preg_match(self::SHA256, $value[$key]) !== 1) {
                throw new RuntimeException('UPGRADE_TARGET_SCAFFOLD_INVALID');
            }
        }
        return $value;
    }

    /** @return array{from:array{inventory_sha256:string,files:list<array{migration_id:string,sha256:string}>},to:array{inventory_sha256:string,files:list<array{migration_id:string,sha256:string}>}} */
    private static function migrations(mixed $value): array
    {
        if (!is_array($value) || array_is_list($value)) {
            throw new RuntimeException('UPGRADE_TARGET_MIGRATION_INVENTORY_INVALID');
        }
        self::exact($value, ['from', 'to']);
        return [
            'from' => self::migrationInventory($value['from'] ?? null),
            'to' => self::migrationInventory($value['to'] ?? null),
        ];
    }

    /** @return array{inventory_sha256:string,files:list<array{migration_id:string,sha256:string}>} */
    private static function migrationInventory(mixed $value): array
    {
        if (!is_array($value) || array_is_list($value)) {
            throw new RuntimeException('UPGRADE_TARGET_MIGRATION_INVENTORY_INVALID');
        }
        self::exact($value, ['inventory_sha256', 'files']);
        if (!is_string($value['inventory_sha256'] ?? null)
            || preg_match(self::SHA256, $value['inventory_sha256']) !== 1
            || !is_array($value['files'] ?? null) || !array_is_list($value['files'])) {
            throw new RuntimeException('UPGRADE_TARGET_MIGRATION_INVENTORY_INVALID');
        }
        $previous = null;
        foreach ($value['files'] as $entry) {
            if (!is_array($entry) || array_is_list($entry)) {
                throw new RuntimeException('UPGRADE_TARGET_MIGRATION_INVENTORY_INVALID');
            }
            self::exact($entry, ['migration_id', 'sha256']);
            if (!is_string($entry['migration_id'] ?? null)
                || preg_match(self::MIGRATION, $entry['migration_id']) !== 1
                || !is_string($entry['sha256'] ?? null)
                || preg_match(self::SHA256, $entry['sha256']) !== 1
                || ($previous !== null && strcmp($previous, $entry['migration_id']) >= 0)) {
                throw new RuntimeException('UPGRADE_TARGET_MIGRATION_INVENTORY_INVALID');
            }
            $previous = $entry['migration_id'];
        }
        $map = self::migrationMap($value['files']);
        $actual = hash('sha256', (string)json_encode($map, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
        if (!hash_equals($value['inventory_sha256'], $actual)) {
            throw new RuntimeException('UPGRADE_TARGET_MIGRATION_INVENTORY_INVALID');
        }
        return $value;
    }

    /** @return array{lock_sha256:string,kernel_version:string} */
    private static function modules(mixed $value): array
    {
        if (!is_array($value) || array_is_list($value)) {
            throw new RuntimeException('UPGRADE_TARGET_MODULE_LOCK_INVALID');
        }
        self::exact($value, ['lock_sha256', 'kernel_version']);
        if (!is_string($value['lock_sha256'] ?? null)
            || preg_match(self::SHA256, $value['lock_sha256']) !== 1
            || !is_string($value['kernel_version'] ?? null)
            || preg_match(self::KERNEL_VERSION, $value['kernel_version']) !== 1) {
            throw new RuntimeException('UPGRADE_TARGET_MODULE_LOCK_INVALID');
        }
        return $value;
    }

    private static function assertDigest(string $path, string $expected): void
    {
        $actual = hash_file('sha256', $path);
        if (!is_string($actual) || !hash_equals($expected, $actual)) {
            throw new RuntimeException('UPGRADE_TARGET_ARTIFACT_MISMATCH');
        }
    }

    /** @return array{source_commit:string,source_tree:string,inventory_sha256:string} */
    private static function assertScaffoldVersion(string $path, string $expected): array
    {
        $manifest = self::json($path, 'UPGRADE_TARGET_SCAFFOLD_INVALID');
        $release = is_array($manifest['release'] ?? null) ? $manifest['release'] : [];
        if (($release['version'] ?? null) !== $expected
            || !is_string($release['source_commit'] ?? null)
            || preg_match(self::COMMIT, $release['source_commit']) !== 1
            || !is_string($release['source_tree'] ?? null)
            || preg_match(self::COMMIT, $release['source_tree']) !== 1
            || !is_string($release['inventory_sha256'] ?? null)
            || preg_match(self::SHA256, $release['inventory_sha256']) !== 1) {
            throw new RuntimeException('UPGRADE_TARGET_SCAFFOLD_INVALID');
        }
        return [
            'source_commit' => $release['source_commit'],
            'source_tree' => $release['source_tree'],
            'inventory_sha256' => $release['inventory_sha256'],
        ];
    }

    /** @param list<array{migration_id:string,sha256:string}> $files @return array<string,string> */
    private static function migrationMap(array $files): array
    {
        $map = [];
        foreach ($files as $entry) {
            $map[$entry['migration_id']] = $entry['sha256'];
        }
        ksort($map, SORT_STRING);
        return $map;
    }

    /** @param list<string> $keys */
    private static function exact(array $value, array $keys): void
    {
        $actual = array_keys($value);
        sort($actual, SORT_STRING);
        sort($keys, SORT_STRING);
        if ($actual !== $keys) {
            throw new RuntimeException('UPGRADE_TARGET_DESCRIPTOR_INVALID');
        }
    }
}
