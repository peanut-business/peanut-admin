<?php
declare(strict_types=1);

namespace app\common\service\scaffold;

use RuntimeException;

final class ScaffoldManifest
{
    private const POLICIES = ['managed', 'generated'];
    private const OWNERS = ['host', 'backend', 'frontend'];
    private const VERSION_PATTERN = '/^(0|[1-9][0-9]*)\.(0|[1-9][0-9]*)\.(0|[1-9][0-9]*)(?:[-+][0-9A-Za-z.-]+)?$/D';

    /** @param array<string,mixed> $data */
    private function __construct(
        public readonly string $path,
        public readonly string $directory,
        public readonly array $data,
    ) {
    }

    public static function load(string $path): self
    {
        $resolved = realpath($path);
        if ($resolved === false || !is_file($resolved)) {
            throw new RuntimeException("SCAFFOLD_MANIFEST_NOT_FOUND: {$path}");
        }
        $raw = file_get_contents($resolved);
        try {
            $data = is_string($raw) ? json_decode($raw, true, 512, JSON_THROW_ON_ERROR) : null;
        } catch (\JsonException $exception) {
            throw new RuntimeException('SCAFFOLD_MANIFEST_INVALID_JSON: ' . $exception->getMessage(), 0, $exception);
        }
        $protocol = $data['protocol'] ?? null;
        $supported = is_array($data) && (
            (($data['schema_version'] ?? null) === 2 && $protocol === 'peanut.scaffold-release.v2')
            || (($data['schema_version'] ?? null) === 3 && $protocol === 'peanut.scaffold-release.v3')
        );
        if (!$supported) {
            throw new RuntimeException('SCAFFOLD_MANIFEST_SCHEMA_UNSUPPORTED');
        }
        $release = $data['release'] ?? null;
        if (!is_array($release) || preg_match('/^\d+\.\d+\.\d+$/D', (string)($release['version'] ?? '')) !== 1
            || preg_match('/^[a-f0-9]{40}$/D', (string)($release['source_commit'] ?? '')) !== 1
            || preg_match('/^[a-f0-9]{40}$/D', (string)($release['source_tree'] ?? '')) !== 1
            || preg_match('/^[a-f0-9]{64}$/D', (string)($release['inventory_sha256'] ?? '')) !== 1
            || !self::nonEmptyString($release['inventory_template_version'] ?? null)
            || !is_array($release['tokens'] ?? null)) {
            throw new RuntimeException('SCAFFOLD_MANIFEST_RELEASE_INVALID');
        }
        if ($protocol === 'peanut.scaffold-release.v3') {
            $application = $data['application'] ?? null;
            if (!is_array($application)
                || array_keys($application) !== ['version']
                || preg_match(self::VERSION_PATTERN, (string)$application['version']) !== 1
                || array_keys($release['tokens']) !== ['product_name', 'slug', 'package_identity', 'application_version']) {
                throw new RuntimeException('SCAFFOLD_MANIFEST_APPLICATION_INVALID');
            }
        } elseif (array_keys($release['tokens']) !== ['product_name', 'slug', 'package_identity']) {
            throw new RuntimeException('SCAFFOLD_MANIFEST_RELEASE_INVALID');
        }
        foreach ($release['tokens'] as $token) {
            if (!self::nonEmptyString($token)) {
                throw new RuntimeException('SCAFFOLD_MANIFEST_RELEASE_INVALID');
            }
        }
        $files = $data['files'] ?? null;
        if (!is_array($files)) {
            throw new RuntimeException('SCAFFOLD_MANIFEST_FILES_INVALID');
        }
        $seen = [];
        foreach ($files as $index => $file) {
            if (!is_array($file)) {
                throw new RuntimeException("SCAFFOLD_MANIFEST_FILE_INVALID: {$index}");
            }
            self::validateFile($file, (string)$index);
            if (isset($seen[$file['path']])) {
                throw new RuntimeException('SCAFFOLD_MANIFEST_PATH_DUPLICATE: ' . $file['path']);
            }
            $seen[$file['path']] = true;
        }
        $renames = $data['renames'] ?? [];
        if (!is_array($renames)) {
            throw new RuntimeException('SCAFFOLD_MANIFEST_RENAMES_INVALID');
        }
        foreach ($renames as $index => $rename) {
            if (!is_array($rename)) {
                throw new RuntimeException("SCAFFOLD_MANIFEST_RENAME_INVALID: {$index}");
            }
            self::path((string)($rename['from'] ?? ''));
            self::path((string)($rename['to'] ?? ''));
            self::owner((string)($rename['owner'] ?? ''));
        }

        return new self($resolved, dirname($resolved), $data);
    }

    public function version(): string
    {
        return (string)$this->data['release']['version'];
    }

    public function supportsApplicationVersion(): bool
    {
        return $this->data['protocol'] === 'peanut.scaffold-release.v3';
    }

    public function defaultApplicationVersion(): ?string
    {
        return $this->supportsApplicationVersion() ? (string)$this->data['application']['version'] : null;
    }

    /** @return array<string,array<string,mixed>> */
    public function files(): array
    {
        $files = [];
        foreach ($this->data['files'] as $file) {
            $files[(string)$file['path']] = $file;
        }
        ksort($files, SORT_STRING);
        return $files;
    }

    /** @return array<int,array<string,mixed>> */
    public function renames(): array
    {
        return array_values($this->data['renames'] ?? []);
    }

    public function digest(): string
    {
        return 'sha256:' . hash_file('sha256', $this->path);
    }

    /** @return array<string,mixed> */
    public function release(): array
    {
        return $this->data['release'];
    }

    public function artifactPath(array $file): string
    {
        $source = (string)($file['source'] ?? ('files/' . $file['path']));
        self::path($source);
        $candidate = $this->directory . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $source);
        return ScaffoldPathGuard::existingFileWithin($this->directory, $candidate, 'SCAFFOLD_ARTIFACT_PATH_INVALID');
    }

    /** @param array<string,mixed> $file */
    private static function validateFile(array $file, string $index): void
    {
        $path = (string)($file['path'] ?? '');
        self::path($path);
        $policy = (string)($file['policy'] ?? '');
        if (!in_array($policy, self::POLICIES, true)) {
            throw new RuntimeException("SCAFFOLD_MANIFEST_POLICY_INVALID: {$path}");
        }
        self::owner((string)($file['owner'] ?? ''));
        $classification = (string)($file['classification'] ?? '');
        if (!in_array($classification, ['managed', 'generated-managed'], true)
            || ($classification === 'generated-managed') !== ($policy === 'generated')
            || ($file['transform'] ?? null) !== 'tokens'
            || !in_array($file['mode'] ?? null, [0644, 0755], true)) {
            throw new RuntimeException("SCAFFOLD_MANIFEST_FILE_INVALID: {$path}");
        }
        $digest = $file['template_sha256'] ?? null;
        if (!is_string($digest) || preg_match('/^[a-f0-9]{64}$/D', $digest) !== 1) {
            throw new RuntimeException("SCAFFOLD_MANIFEST_DIGEST_INVALID: {$path}");
        }
    }

    public static function path(string $path): string
    {
        if ($path === '' || str_contains($path, "\0") || str_contains($path, '\\')
            || str_starts_with($path, '/') || preg_match('/^[A-Za-z]:/', $path) === 1) {
            throw new RuntimeException("SCAFFOLD_PATH_OUTSIDE_PROJECT: {$path}");
        }
        $segments = explode('/', $path);
        foreach ($segments as $segment) {
            if ($segment === '' || $segment === '.' || $segment === '..') {
                throw new RuntimeException("SCAFFOLD_PATH_OUTSIDE_PROJECT: {$path}");
            }
        }
        return $path;
    }

    private static function owner(string $owner): void
    {
        if (!in_array($owner, self::OWNERS, true)) {
            throw new RuntimeException("SCAFFOLD_MANIFEST_OWNER_INVALID: {$owner}");
        }
    }

    private static function nonEmptyString(mixed $value): bool
    {
        return is_string($value) && trim($value) !== '';
    }
}
