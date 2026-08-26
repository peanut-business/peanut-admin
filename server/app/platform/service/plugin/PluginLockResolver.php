<?php
declare(strict_types=1);

namespace app\platform\service\plugin;

/** Resolves only explicitly locked Plugin manifests; it never scans vendor or application directories. */
final class PluginLockResolver
{
    /** @var array<string,PluginDescriptor>|null */
    private ?array $resolved = null;

    public function __construct(
        private readonly string $serverRoot,
        private readonly string $lockPath
    ) {
    }

    /** @return array<string,PluginDescriptor> */
    public function all(): array
    {
        if ($this->resolved !== null) {
            return $this->resolved;
        }
        $lockPath = $this->absolutePath($this->lockPath, $this->serverRoot);
        $lock = $this->readJson($lockPath, 'PLUGIN_LOCK_INVALID');
        $this->assertExactKeys($lock, ['schema_version', 'plugins'], 'PLUGIN_LOCK_INVALID');
        if (($lock['schema_version'] ?? null) !== 1 || !is_array($lock['plugins'] ?? null)
            || !array_is_list($lock['plugins'])) {
            throw new PluginLifecycleException('PLUGIN_LOCK_INVALID', 'plugins.lock schema is invalid.');
        }
        $lockDigest = hash_file('sha256', $lockPath);
        if (!is_string($lockDigest)) {
            throw new PluginLifecycleException('PLUGIN_LOCK_INVALID', 'plugins.lock digest is unavailable.');
        }
        $base = dirname($lockPath);
        $plugins = [];
        foreach ($lock['plugins'] as $entry) {
            if (!is_array($entry)) {
                throw new PluginLifecycleException('PLUGIN_LOCK_INVALID', 'Plugin lock entry is invalid.');
            }
            $this->assertExactKeys(
                $entry,
                ['key', 'version', 'source', 'manifest', 'manifest_sha256', 'composer', 'npm', 'frontend', 'modules'],
                'PLUGIN_LOCK_INVALID'
            );
            $key = $this->key($entry['key'] ?? null, 'PLUGIN_LOCK_INVALID');
            $version = $this->version($entry['version'] ?? null, 'PLUGIN_LOCK_INVALID');
            if (isset($plugins[$key])) {
                throw new PluginLifecycleException('PLUGIN_LOCK_INVALID', "Duplicate Plugin key: {$key}");
            }
            $source = $this->source($entry['source'] ?? null);
            $manifestPath = $this->absolutePath($this->text($entry['manifest'] ?? null), $base);
            $expectedManifestDigest = $this->sha256($entry['manifest_sha256'] ?? null, 'PLUGIN_LOCK_INVALID');
            $manifestDigest = hash_file('sha256', $manifestPath);
            if (!is_string($manifestDigest) || !hash_equals($expectedManifestDigest, $manifestDigest)) {
                throw new PluginLifecycleException('PLUGIN_ARTIFACT_MISMATCH', "Plugin manifest digest differs: {$key}");
            }
            $manifest = $this->readJson($manifestPath, 'PLUGIN_MANIFEST_INVALID');
            $this->assertExactKeys(
                $manifest,
                ['schema_version', 'key', 'version', 'source', 'composer', 'npm', 'frontend', 'modules'],
                'PLUGIN_MANIFEST_INVALID'
            );
            if (($manifest['schema_version'] ?? null) !== 1
                || $manifest['key'] !== $key
                || $manifest['version'] !== $version
                || !$this->sameJson($manifest['source'] ?? null, $source)
                || !$this->sameJson($manifest['composer'] ?? null, $entry['composer'] ?? null)
                || !$this->sameJson($manifest['npm'] ?? null, $entry['npm'] ?? null)
                || !$this->sameJson($manifest['frontend'] ?? null, $entry['frontend'] ?? null)
                || !$this->sameJson($manifest['modules'] ?? null, $entry['modules'] ?? null)) {
                throw new PluginLifecycleException('PLUGIN_MANIFEST_MISMATCH', "Plugin manifest differs from lock: {$key}");
            }
            $composer = $this->identityList($entry['composer'] ?? null, ['name', 'version', 'sha256'], 'composer');
            $npm = $this->identityList($entry['npm'] ?? null, ['name', 'version', 'integrity'], 'npm');
            $frontend = $this->identityList(
                $entry['frontend'] ?? null,
                ['client_key', 'package', 'version', 'entry', 'sha256'],
                'frontend'
            );
            $projectRoot = dirname($this->serverRoot);
            $moduleRoots = $this->resolveModuleRoots($entry['modules'] ?? null, $projectRoot);
            $frontendRoots = $this->frontendRoots($moduleRoots, $projectRoot);
            $this->verifyPackageIdentities(
                $composer,
                $npm,
                $frontend,
                $moduleRoots,
                $frontendRoots,
                $projectRoot
            );
            $this->verifySource(
                $source,
                [...array_values($moduleRoots), ...array_values($frontendRoots)]
            );
            $plugins[$key] = new PluginDescriptor(
                $key,
                $version,
                $source,
                $lockDigest,
                $manifestDigest,
                $composer,
                $npm,
                $frontend,
                $moduleRoots
            );
        }
        return $this->resolved = $plugins;
    }

    public function require(string $pluginKey): PluginDescriptor
    {
        return $this->all()[$pluginKey]
            ?? throw new PluginLifecycleException('PLUGIN_NOT_LOCKED', "Plugin is not locked: {$pluginKey}");
    }

    /** @return list<string> */
    public function moduleRoots(): array
    {
        $roots = [];
        foreach ($this->all() as $plugin) {
            foreach ($plugin->moduleRoots as $root) {
                if (in_array($root, $roots, true)) {
                    throw new PluginLifecycleException('PLUGIN_MODULE_CONFLICT', "Duplicate Module root: {$root}");
                }
                $roots[] = $root;
            }
        }
        return $roots;
    }

    /** @return array{type:string,reference:string,sha256:string} */
    private function source(mixed $source): array
    {
        if (!is_array($source)) {
            throw new PluginLifecycleException('PLUGIN_LOCK_INVALID', 'Plugin source is invalid.');
        }
        $this->assertExactKeys($source, ['type', 'reference', 'sha256'], 'PLUGIN_LOCK_INVALID');
        $type = $this->text($source['type'] ?? null);
        if (!in_array($type, ['canonical-contents', 'composer', 'npm', 'release'], true)) {
            throw new PluginLifecycleException('PLUGIN_SOURCE_INVALID', 'Plugin source type is unsupported.');
        }
        return [
            'type' => $type,
            'reference' => $this->text($source['reference'] ?? null),
            'sha256' => $this->sha256($source['sha256'] ?? null, 'PLUGIN_SOURCE_INVALID'),
        ];
    }

    /** @param array{type:string,reference:string,sha256:string} $source */
    private function verifySource(array $source, array $moduleRoots): void
    {
        if ($source['type'] !== 'canonical-contents') {
            return;
        }
        $projectRoot = realpath(dirname($this->serverRoot)) ?: dirname($this->serverRoot);
        $files = [];
        foreach ($moduleRoots as $directory) {
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS)
            );
            foreach ($iterator as $file) {
                if ($file->isFile() && !$file->isLink()) {
                    $path = $file->getRealPath() ?: $file->getPathname();
                    $relative = str_replace(
                        '\\',
                        '/',
                        substr($path, strlen($projectRoot) + 1)
                    );
                    $digest = hash_file('sha256', $file->getPathname());
                    if (!is_string($digest) || isset($files[$relative])) {
                        throw new PluginLifecycleException('PLUGIN_SOURCE_INVALID', 'Plugin contents are invalid.');
                    }
                    $files[$relative] = $digest;
                }
            }
        }
        ksort($files, SORT_STRING);
        $canonical = '';
        foreach ($files as $relative => $digest) {
            $canonical .= $relative . "\0" . $digest . "\n";
        }
        $computed = hash('sha256', $canonical);
        if (!hash_equals($source['sha256'], $computed)) {
            file_put_contents('/tmp/canonical-debug.txt', $canonical);
            file_put_contents('/tmp/canonical-computed.txt', "Expected: {$source['sha256']}\nComputed: {$computed}\n");
            throw new PluginLifecycleException('PLUGIN_ARTIFACT_MISMATCH', 'Canonical Plugin contents digest differs.');
        }
    }

    /**
     * @param list<array<string,mixed>> $composer
     * @param list<array<string,mixed>> $npm
     * @param list<array<string,mixed>> $frontend
     * @param array<string,string> $moduleRoots
     * @param array<string,string> $frontendRoots
     */
    private function verifyPackageIdentities(
        array $composer,
        array $npm,
        array $frontend,
        array $moduleRoots,
        array $frontendRoots,
        string $projectRoot
    ): void {
        $composerFiles = $this->packageFiles($moduleRoots, 'composer.json');
        foreach ($composer as $identity) {
            $path = $this->matchingPackageFile(
                $composerFiles,
                (string)$identity['name'],
                (string)$identity['version'],
                'composer'
            );
            $this->assertFileDigest($path, (string)$identity['sha256'], 'composer');
        }
        if (count($composer) !== count($composerFiles)) {
            throw new PluginLifecycleException('PLUGIN_IDENTITY_INVALID', 'Composer identity coverage is incomplete.');
        }

        $npmFiles = $this->packageFiles($frontendRoots, 'package.json');
        foreach ($npm as $identity) {
            $path = $this->matchingPackageFile(
                $npmFiles,
                (string)$identity['name'],
                (string)$identity['version'],
                'npm'
            );
            $digest = hash_file('sha256', $path, true);
            $integrity = is_string($digest) ? 'sha256-' . base64_encode($digest) : '';
            if (!hash_equals((string)$identity['integrity'], $integrity)) {
                throw new PluginLifecycleException('PLUGIN_ARTIFACT_MISMATCH', 'npm package identity digest differs.');
            }
        }
        if (count($npm) !== count($npmFiles)) {
            throw new PluginLifecycleException('PLUGIN_IDENTITY_INVALID', 'npm identity coverage is incomplete.');
        }

        foreach ($frontend as $identity) {
            $path = $this->absolutePathWithin(
                (string)$identity['entry'],
                $projectRoot,
                $projectRoot,
                'PLUGIN_IDENTITY_INVALID'
            );
            if (!$this->belongsToModuleRoot($path, $frontendRoots)) {
                throw new PluginLifecycleException(
                    'PLUGIN_IDENTITY_INVALID',
                    'Frontend contribution is outside the Plugin Module roots.'
                );
            }
            $this->assertFileDigest($path, (string)$identity['sha256'], 'frontend');
            $packageMatched = false;
            foreach ($npm as $npmIdentity) {
                if ($npmIdentity['name'] === $identity['package']
                    && $npmIdentity['version'] === $identity['version']) {
                    $packageMatched = true;
                    break;
                }
            }
            if (!$packageMatched) {
                throw new PluginLifecycleException(
                    'PLUGIN_IDENTITY_INVALID',
                    'Frontend contribution has no matching npm package identity.'
                );
            }
        }
    }

    /** @param array<string,string> $moduleRoots @return array<string,string> */
    private function frontendRoots(array $moduleRoots, string $projectRoot): array
    {
        $roots = [];
        foreach ($moduleRoots as $moduleKey => $_backendRoot) {
            $roots[$moduleKey] = $this->absolutePathWithin(
                'web/src/modules/' . str_replace('.', '-', $moduleKey),
                $projectRoot,
                $projectRoot . '/web/src/modules',
                'PLUGIN_PATH_UNAVAILABLE'
            );
        }
        return $roots;
    }

    /** @param array<string,string> $moduleRoots @return list<string> */
    private function packageFiles(array $moduleRoots, string $file): array
    {
        $paths = [];
        foreach ($moduleRoots as $root) {
            $path = $root . '/' . $file;
            if (is_file($path)) {
                $paths[] = $path;
            }
        }
        return $paths;
    }

    /** @param list<string> $paths */
    private function matchingPackageFile(
        array $paths,
        string $name,
        string $version,
        string $kind
    ): string {
        $matches = [];
        foreach ($paths as $path) {
            $document = $this->readJson($path, 'PLUGIN_IDENTITY_INVALID');
            if (($document['name'] ?? null) === $name && ($document['version'] ?? null) === $version) {
                $matches[] = $path;
            }
        }
        if (count($matches) !== 1) {
            throw new PluginLifecycleException(
                'PLUGIN_IDENTITY_INVALID',
                "Plugin {$kind} identity does not resolve to exactly one Module package."
            );
        }
        return $matches[0];
    }

    private function assertFileDigest(string $path, string $expected, string $kind): void
    {
        $actual = hash_file('sha256', $path);
        if (!is_string($actual) || !hash_equals($expected, $actual)) {
            throw new PluginLifecycleException(
                'PLUGIN_ARTIFACT_MISMATCH',
                "Plugin {$kind} identity digest differs."
            );
        }
    }

    /** @param array<string,string> $moduleRoots */
    private function belongsToModuleRoot(string $path, array $moduleRoots): bool
    {
        foreach ($moduleRoots as $root) {
            if ($path === $root || str_starts_with($path, $root . DIRECTORY_SEPARATOR)) {
                return true;
            }
        }
        return false;
    }

    /** @return list<array<string,mixed>> */
    private function identityList(mixed $value, array $keys, string $kind): array
    {
        if (!is_array($value) || !array_is_list($value)) {
            throw new PluginLifecycleException('PLUGIN_IDENTITY_INVALID', "Plugin {$kind} identity is invalid.");
        }
        $seen = [];
        foreach ($value as $identity) {
            if (!is_array($identity)) {
                throw new PluginLifecycleException('PLUGIN_IDENTITY_INVALID', "Plugin {$kind} identity is invalid.");
            }
            $this->assertExactKeys($identity, $keys, 'PLUGIN_IDENTITY_INVALID');
            foreach ($keys as $key) {
                $this->text($identity[$key] ?? null);
            }
            if (isset($identity['sha256'])) {
                $this->sha256($identity['sha256'], 'PLUGIN_IDENTITY_INVALID');
            }
            $unique = (string)($identity['name'] ?? $identity['package'] ?? $identity['client_key'] ?? '');
            if (isset($seen[$unique])) {
                throw new PluginLifecycleException('PLUGIN_IDENTITY_INVALID', "Duplicate {$kind} identity: {$unique}");
            }
            $seen[$unique] = true;
        }
        return $value;
    }

    /** @return array<string,string> */
    private function resolveModuleRoots(mixed $modules, string $base): array
    {
        if (!is_array($modules) || $modules === [] || !array_is_list($modules)) {
            throw new PluginLifecycleException('PLUGIN_MANIFEST_INVALID', 'Plugin modules are invalid.');
        }
        $roots = [];
        foreach ($modules as $module) {
            if (!is_array($module)) {
                throw new PluginLifecycleException('PLUGIN_MANIFEST_INVALID', 'Plugin Module is invalid.');
            }
            $this->assertExactKeys($module, ['key', 'root'], 'PLUGIN_MANIFEST_INVALID');
            $key = $this->key($module['key'] ?? null, 'PLUGIN_MANIFEST_INVALID');
            if (isset($roots[$key])) {
                throw new PluginLifecycleException('PLUGIN_MODULE_CONFLICT', "Duplicate Module key: {$key}");
            }
            $roots[$key] = $this->absolutePathWithin(
                $this->text($module['root'] ?? null),
                $base,
                $base,
                'PLUGIN_PATH_UNAVAILABLE'
            );
            if (!is_dir($roots[$key]) || !is_file($roots[$key] . '/module.json')) {
                throw new PluginLifecycleException('PLUGIN_MANIFEST_INVALID', "Module root is unavailable: {$key}");
            }
        }
        ksort($roots, SORT_STRING);
        return $roots;
    }

    /** @return array<string,mixed> */
    private function readJson(string $path, string $error): array
    {
        if (!is_file($path)) {
            throw new PluginLifecycleException($error, "JSON file is unavailable: {$path}");
        }
        try {
            $decoded = json_decode((string)file_get_contents($path), true, 64, JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            throw new PluginLifecycleException($error, $exception->getMessage());
        }
        if (!is_array($decoded)) {
            throw new PluginLifecycleException($error, 'JSON document must be an object.');
        }
        return $decoded;
    }

    private function absolutePath(string $path, string $base): string
    {
        $candidate = str_starts_with($path, DIRECTORY_SEPARATOR) ? $path : $base . '/' . ltrim($path, '/');
        $resolved = realpath($candidate);
        if ($resolved === false) {
            throw new PluginLifecycleException('PLUGIN_PATH_UNAVAILABLE', "Plugin path is unavailable: {$path}");
        }
        return $resolved;
    }

    private function absolutePathWithin(string $path, string $base, string $boundary, string $error): string
    {
        $resolved = $this->absolutePath($path, $base);
        $root = realpath($boundary);
        if ($root === false || ($resolved !== $root && !str_starts_with($resolved, $root . DIRECTORY_SEPARATOR))) {
            throw new PluginLifecycleException($error, "Plugin path escapes its allowed root: {$path}");
        }
        return $resolved;
    }

    private function key(mixed $value, string $error): string
    {
        $value = $this->text($value);
        if (preg_match('/^[a-z][a-z0-9]*(?:[.-][a-z0-9]+)*$/D', $value) !== 1 || strlen($value) > 96) {
            throw new PluginLifecycleException($error, "Invalid Plugin/Module key: {$value}");
        }
        return $value;
    }

    private function version(mixed $value, string $error): string
    {
        $value = $this->text($value);
        if (preg_match('/^[0-9]+\.[0-9]+\.[0-9]+(?:[-+][0-9A-Za-z.-]+)?$/D', $value) !== 1 || strlen($value) > 32) {
            throw new PluginLifecycleException($error, "Invalid Plugin version: {$value}");
        }
        return $value;
    }

    private function sha256(mixed $value, string $error): string
    {
        $value = $this->text($value);
        if (preg_match('/^[a-f0-9]{64}$/D', $value) !== 1) {
            throw new PluginLifecycleException($error, 'SHA-256 identity is invalid.');
        }
        return $value;
    }

    private function text(mixed $value): string
    {
        if (!is_string($value) || trim($value) === '') {
            throw new PluginLifecycleException('PLUGIN_MANIFEST_INVALID', 'Required Plugin text is invalid.');
        }
        return trim($value);
    }

    /** @param list<string> $keys */
    private function assertExactKeys(array $value, array $keys, string $error): void
    {
        $actual = array_keys($value);
        sort($actual, SORT_STRING);
        sort($keys, SORT_STRING);
        if ($actual !== $keys) {
            throw new PluginLifecycleException($error, 'Plugin document fields do not match the schema.');
        }
    }

    private function sameJson(mixed $left, mixed $right): bool
    {
        return $this->canonicalJson($left) === $this->canonicalJson($right);
    }

    private function canonicalJson(mixed $value): string
    {
        $normalize = static function (mixed $item) use (&$normalize): mixed {
            if (!is_array($item)) {
                return $item;
            }
            $isList = array_is_list($item);
            if (!$isList) {
                ksort($item, SORT_STRING);
            }
            foreach ($item as $key => $child) {
                $item[$key] = $normalize($child);
            }
            if ($isList) {
                usort($item, static fn(mixed $left, mixed $right): int => strcmp(
                    (string)json_encode($left, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES),
                    (string)json_encode($right, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES)
                ));
            }
            return $item;
        };
        return (string)json_encode($normalize($value), JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
    }
}
