<?php
declare(strict_types=1);

namespace app\common\service\scaffold;

use RuntimeException;

final class ScaffoldManifest
{
    private const POLICIES = ['managed', 'merge', 'preserve', 'generated', 'deprecated', 'manual'];
    private const OWNERS = ['host', 'backend', 'frontend'];

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
        if (!is_array($data) || ($data['schema_version'] ?? null) !== 1) {
            throw new RuntimeException('SCAFFOLD_MANIFEST_SCHEMA_UNSUPPORTED');
        }
        $release = $data['release'] ?? null;
        if (!is_array($release) || !self::nonEmptyString($release['version'] ?? null)) {
            throw new RuntimeException('SCAFFOLD_MANIFEST_RELEASE_INVALID');
        }
        $files = $data['files'] ?? null;
        if (!is_array($files)) {
            throw new RuntimeException('SCAFFOLD_MANIFEST_FILES_INVALID');
        }
        foreach ($files as $index => $file) {
            if (!is_array($file)) {
                throw new RuntimeException("SCAFFOLD_MANIFEST_FILE_INVALID: {$index}");
            }
            self::validateFile($file, (string)$index);
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
        $digest = $file['content_sha256'] ?? null;
        if ($policy !== 'deprecated' && (!is_string($digest) || preg_match('/^[a-f0-9]{64}$/D', $digest) !== 1)) {
            throw new RuntimeException("SCAFFOLD_MANIFEST_DIGEST_INVALID: {$path}");
        }
        if ($policy === 'deprecated' && isset($file['renamed_to'])) {
            self::path((string)$file['renamed_to']);
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
