<?php
declare(strict_types=1);

namespace app\common\service\scaffold;

use RuntimeException;

final class EditionUpgradePackage
{
    private const VERSION = '/^(0|[1-9][0-9]*)\.(0|[1-9][0-9]*)\.(0|[1-9][0-9]*)$/D';

    /** @return array{from_manifest:string,to_manifest:string,package:array<string,mixed>} */
    public function prepare(string $projectRoot, string $packageRoot, string $signatureKeyId): array
    {
        $project = ScaffoldPathGuard::projectRoot($projectRoot);
        $package = realpath($packageRoot);
        if (!is_string($package) || !is_dir($package) || is_link($package)) {
            throw new RuntimeException('EDITION_UPGRADE_PACKAGE_NOT_FOUND');
        }
        if (preg_match('/^[A-Za-z0-9._-]{1,96}$/D', $signatureKeyId) !== 1) {
            throw new RuntimeException('EDITION_UPGRADE_SIGNATURE_KEY_INVALID');
        }

        $inventoryPath = $package . '/META-INF/files.sha256';
        if (!is_file($inventoryPath) || is_link($inventoryPath)) {
            throw new RuntimeException('EDITION_UPGRADE_INVENTORY_MISSING');
        }
        $inventory = (string)file_get_contents($inventoryPath);
        $files = $this->verifyInventory($package, $inventory);
        $this->verifySignature($package, $inventory, $signatureKeyId);

        $manifestPath = $package . '/upgrade-manifest.json';
        if (!isset($files['upgrade-manifest.json'])) {
            throw new RuntimeException('EDITION_UPGRADE_MANIFEST_MISSING');
        }
        try {
            $manifest = json_decode((string)file_get_contents($manifestPath), true, 128, JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            throw new RuntimeException('EDITION_UPGRADE_MANIFEST_INVALID', 0, $exception);
        }
        if (!is_array($manifest)
            || ($manifest['schema_version'] ?? null) !== 1
            || ($manifest['protocol'] ?? null) !== 'peanut.edition-upgrade-package.v1'
            || ($manifest['upgrader']['entrypoint'] ?? null) !== 'upgrader/scripts/scaffold-upgrade'
            || !isset($files['upgrader/scripts/scaffold-upgrade'], $files['upgrader/scripts/scaffold-runtime/EditionUpgradePackage.php'])) {
            throw new RuntimeException('EDITION_UPGRADE_MANIFEST_INVALID');
        }

        $application = $this->applicationManifest($project);
        $edition = (string)($manifest['edition']['name'] ?? '');
        if (!in_array($edition, ['standalone', 'multi-tenant'], true)
            || ($application['application']['edition'] ?? null) !== $edition
            || ($application['edition']['name'] ?? null) !== $edition) {
            throw new RuntimeException('EDITION_UPGRADE_EDITION_MISMATCH');
        }
        if (($manifest['signing']['algorithm'] ?? null) !== 'ed25519'
            || ($manifest['signing']['authority'] ?? null) !== $signatureKeyId) {
            throw new RuntimeException('EDITION_UPGRADE_AUTHORITY_MISMATCH');
        }

        $current = (string)($application['template']['version'] ?? '');
        $minimum = (string)($manifest['compatibility']['source']['minimum_inclusive'] ?? '');
        $maximum = (string)($manifest['compatibility']['source']['maximum_exclusive'] ?? '');
        $target = (string)($manifest['target']['version'] ?? '');
        if (preg_match(self::VERSION, $current) !== 1
            || preg_match(self::VERSION, $minimum) !== 1
            || preg_match(self::VERSION, $maximum) !== 1
            || preg_match(self::VERSION, $target) !== 1
            || $maximum !== $target
            || ($manifest['compatibility']['major_policy'] ?? null) !== 'same-major'
            || version_compare($minimum, $target, '>=')
            || version_compare($current, $minimum, '<')
            || version_compare($current, $target, '>=')
            || explode('.', $current, 2)[0] !== explode('.', $target, 2)[0]) {
            throw new RuntimeException('EDITION_UPGRADE_RELEASE_CHAIN_INVALID');
        }

        $targetRelative = (string)($manifest['target']['scaffold_manifest'] ?? '');
        ScaffoldManifest::path($targetRelative);
        if (!isset($files[$targetRelative])) {
            throw new RuntimeException('EDITION_UPGRADE_TARGET_MANIFEST_MISSING');
        }
        $targetPath = $package . '/' . $targetRelative;
        $targetManifest = ScaffoldManifest::load($targetPath);
        $targetDigest = hash_file('sha256', $targetPath);
        $release = $targetManifest->release();
        if (!is_string($targetDigest)
            || !hash_equals((string)($manifest['target']['scaffold_manifest_sha256'] ?? ''), $targetDigest)
            || $targetManifest->version() !== $target
            || ($targetManifest->data['edition']['name'] ?? null) !== $edition
            || ($release['source_commit'] ?? null) !== ($manifest['build_source']['commit'] ?? null)
            || ($release['source_tree'] ?? null) !== ($manifest['build_source']['tree'] ?? null)
            || ($release['inventory_sha256'] ?? null) !== ($manifest['build_source']['inventory_sha256'] ?? null)
            || ($release['managed_tree_sha256'] ?? null) !== ($manifest['target']['managed_tree_sha256'] ?? null)) {
            throw new RuntimeException('EDITION_UPGRADE_TARGET_IDENTITY_MISMATCH');
        }

        $this->assertOwnership($manifest);
        $this->assertMigrationChain($manifest, $targetManifest);

        return [
            'from_manifest' => $this->writeBaselineManifest($project, $application),
            'to_manifest' => $targetManifest->path,
            'package' => $manifest + [
                'inventory_sha256' => hash('sha256', $inventory),
                'signature_key_id' => $signatureKeyId,
            ],
        ];
    }

    /** @return array<string,string> */
    private function verifyInventory(string $root, string $contents): array
    {
        $inventory = [];
        $lines = explode("\n", $contents);
        if (array_pop($lines) !== '') {
            throw new RuntimeException('EDITION_UPGRADE_INVENTORY_INVALID');
        }
        $previous = null;
        foreach ($lines as $line) {
            $separator = strpos($line, "\0");
            if ($separator === false || strpos($line, "\0", $separator + 1) !== false) {
                throw new RuntimeException('EDITION_UPGRADE_INVENTORY_INVALID');
            }
            $path = substr($line, 0, $separator);
            $digest = substr($line, $separator + 1);
            ScaffoldManifest::path($path);
            if (preg_match('/^[a-f0-9]{64}$/D', $digest) !== 1
                || isset($inventory[$path])
                || ($previous !== null && strcmp($previous, $path) >= 0)) {
                throw new RuntimeException('EDITION_UPGRADE_INVENTORY_INVALID');
            }
            $absolute = $root . '/' . $path;
            if (!is_file($absolute) || is_link($absolute)
                || !hash_equals($digest, (string)hash_file('sha256', $absolute))) {
                throw new RuntimeException('EDITION_UPGRADE_FILE_DIGEST_MISMATCH: ' . $path);
            }
            $inventory[$path] = $digest;
            $previous = $path;
        }

        $actual = [];
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS));
        foreach ($iterator as $file) {
            if ($file->isLink() || !$file->isFile()) {
                throw new RuntimeException('EDITION_UPGRADE_FILE_TYPE_INVALID');
            }
            $relative = str_replace('\\', '/', substr($file->getPathname(), strlen($root) + 1));
            if ($relative === 'META-INF/files.sha256' || str_starts_with($relative, 'META-INF/signatures/')) {
                continue;
            }
            $actual[] = $relative;
        }
        sort($actual, SORT_STRING);
        if ($actual !== array_keys($inventory)) {
            throw new RuntimeException('EDITION_UPGRADE_INVENTORY_COVERAGE_MISMATCH');
        }
        return $inventory;
    }

    private function verifySignature(string $root, string $inventory, string $keyId): void
    {
        $trusted = json_decode((string)getenv('PEANUT_UPGRADE_TRUSTED_KEYS_JSON'), true);
        $public = is_array($trusted) && !array_is_list($trusted)
            ? base64_decode((string)($trusted[$keyId] ?? ''), true)
            : false;
        $path = $root . '/META-INF/signatures/' . $keyId . '.json';
        try {
            $signature = is_file($path) && !is_link($path)
                ? json_decode((string)file_get_contents($path), true, 32, JSON_THROW_ON_ERROR)
                : null;
        } catch (\JsonException $exception) {
            throw new RuntimeException('EDITION_UPGRADE_SIGNATURE_INVALID', 0, $exception);
        }
        $bytes = is_array($signature) ? base64_decode((string)($signature['signature_base64'] ?? ''), true) : false;
        if (!is_string($public) || strlen($public) !== SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES
            || !is_array($signature)
            || ($signature['schema_version'] ?? null) !== 1
            || ($signature['algorithm'] ?? null) !== 'ed25519'
            || ($signature['key_id'] ?? null) !== $keyId
            || !hash_equals(hash('sha256', $inventory), (string)($signature['inventory_sha256'] ?? ''))
            || !is_string($bytes) || strlen($bytes) !== SODIUM_CRYPTO_SIGN_BYTES
            || !sodium_crypto_sign_verify_detached($bytes, hash('sha256', $inventory, true), $public)) {
            throw new RuntimeException('EDITION_UPGRADE_SOURCE_UNTRUSTED');
        }
    }

    /** @return array<string,mixed> */
    private function applicationManifest(string $root): array
    {
        $path = ScaffoldPathGuard::projectPath($root, '.peanut/application-manifest.json');
        try {
            $manifest = is_file($path) && !is_link($path)
                ? json_decode((string)file_get_contents($path), true, 512, JSON_THROW_ON_ERROR)
                : null;
        } catch (\JsonException $exception) {
            throw new RuntimeException('EDITION_UPGRADE_APPLICATION_MANIFEST_INVALID', 0, $exception);
        }
        if (!is_array($manifest)
            || ($manifest['protocol'] ?? null) !== 'peanut.application-scaffold.v2'
            || !is_array($manifest['application'] ?? null)
            || !is_array($manifest['edition'] ?? null)
            || !is_array($manifest['template'] ?? null)
            || !is_array($manifest['files'] ?? null)) {
            throw new RuntimeException('EDITION_UPGRADE_APPLICATION_MANIFEST_INVALID');
        }
        return $manifest;
    }

    /** @param array<string,mixed> $manifest */
    private function assertOwnership(array $manifest): void
    {
        if (($manifest['ownership']['automatic'] ?? null) !== ['managed', 'generated-managed']
            || ($manifest['ownership']['preserved'] ?? null) !== ['app-owned', 'third-party-module', 'secret']
            || ($manifest['recovery']['managed_files'] ?? null) !== 'scaffold-recovery-plan'
            || ($manifest['recovery']['database'] ?? null) !== 'operator-backup-required') {
            throw new RuntimeException('EDITION_UPGRADE_OWNERSHIP_INVALID');
        }
    }

    /** @param array<string,mixed> $manifest */
    private function assertMigrationChain(array $manifest, ScaffoldManifest $target): void
    {
        $chain = $manifest['migration_chain'] ?? null;
        if (!is_array($chain) || ($chain['strategy'] ?? null) !== 'append-only-ledger' || !is_array($chain['files'] ?? null)) {
            throw new RuntimeException('EDITION_UPGRADE_MIGRATION_CHAIN_INVALID');
        }
        $expected = [];
        foreach ($target->files() as $path => $file) {
            if (str_starts_with($path, 'server/database/migrations/') && str_ends_with($path, '.sql')) {
                $expected[$path] = $file['template_sha256'];
            }
        }
        $actual = [];
        foreach ($chain['files'] as $file) {
            if (!is_array($file) || !is_string($file['path'] ?? null) || !is_string($file['sha256'] ?? null)
                || isset($actual[$file['path']])) {
                throw new RuntimeException('EDITION_UPGRADE_MIGRATION_CHAIN_INVALID');
            }
            $actual[$file['path']] = $file['sha256'];
        }
        if ($actual !== $expected) {
            throw new RuntimeException('EDITION_UPGRADE_MIGRATION_CHAIN_INCOMPLETE');
        }
    }

    /** @param array<string,mixed> $application */
    private function writeBaselineManifest(string $root, array $application): string
    {
        $version = (string)$application['template']['version'];
        $baselineRoot = '.peanut/scaffold-baseline/' . $version;
        $files = [];
        foreach ($application['files'] as $file) {
            if (!is_array($file) || !in_array($file['classification'] ?? null, ['managed', 'generated-managed'], true)) {
                continue;
            }
            $path = (string)($file['path'] ?? '');
            ScaffoldManifest::path($path);
            $expectedBaseline = $baselineRoot . '/files/' . $path;
            if (($file['baseline_path'] ?? null) !== $expectedBaseline) {
                throw new RuntimeException('EDITION_UPGRADE_BASELINE_PATH_INVALID: ' . $path);
            }
            $absolute = ScaffoldPathGuard::projectPath($root, $expectedBaseline);
            $digest = hash_file('sha256', $absolute);
            $expectedDigest = (string)($file['baseline_sha256'] ?? $file['sha256'] ?? '');
            if (!is_string($digest) || preg_match('/^[a-f0-9]{64}$/D', $expectedDigest) !== 1
                || !hash_equals($expectedDigest, $digest)) {
                throw new RuntimeException('EDITION_UPGRADE_BASELINE_DRIFT: ' . $path);
            }
            $files[] = [
                'path' => $path,
                'source' => 'files/' . $path,
                'template_sha256' => $digest,
                'classification' => $file['classification'],
                'transform' => 'tokens',
                'mode' => $file['mode'],
                'policy' => $file['classification'] === 'generated-managed' ? 'generated' : 'managed',
                'owner' => $this->owner($path),
            ];
        }
        usort($files, static fn(array $left, array $right): int => strcmp($left['path'], $right['path']));
        $manifest = [
            'schema_version' => 3,
            'protocol' => 'peanut.scaffold-release.v3',
            'application' => ['version' => (string)$application['application']['version']],
            'release' => [
                'version' => $version,
                'source_commit' => $application['template']['source_commit'],
                'source_tree' => $application['template']['source_tree'],
                'inventory_sha256' => $application['template']['inventory_sha256'],
                'inventory_template_version' => $version,
                'managed_tree_sha256' => $application['digests']['managed_tree_sha256'],
                'tokens' => [
                    'product_name' => '__PEANUT_BASELINE_PRODUCT_NAME__',
                    'slug' => '__PEANUT_BASELINE_SLUG__',
                    'package_identity' => '__PEANUT_BASELINE_PACKAGE_IDENTITY__',
                    'application_version' => '__PEANUT_BASELINE_APPLICATION_VERSION__',
                ],
            ],
            'files' => $files,
            'renames' => [],
        ];
        $path = ScaffoldPathGuard::projectPath($root, $baselineRoot . '/edition-scaffold-manifest.json');
        $json = json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) . "\n";
        if (is_file($path)) {
            if (!hash_equals($json, (string)file_get_contents($path))) {
                throw new RuntimeException('EDITION_UPGRADE_BASELINE_MANIFEST_DRIFT');
            }
            return $path;
        }
        ScaffoldPathGuard::ensureDirectory(dirname($path));
        $temporary = $path . '.stage-' . bin2hex(random_bytes(6));
        if (file_put_contents($temporary, $json, LOCK_EX) === false || !chmod($temporary, 0600) || !rename($temporary, $path)) {
            @unlink($temporary);
            throw new RuntimeException('EDITION_UPGRADE_BASELINE_MANIFEST_WRITE_FAILED');
        }
        return $path;
    }

    private function owner(string $path): string
    {
        if (str_starts_with($path, 'server/')) return 'backend';
        if (str_starts_with($path, 'web/') || str_starts_with($path, 'platform/')
            || str_starts_with($path, 'pc/') || str_starts_with($path, 'uniapp/')
            || str_starts_with($path, 'docs-site/')) return 'frontend';
        return 'host';
    }
}
