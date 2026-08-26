<?php
declare(strict_types=1);

namespace app\common\service\module;

use app\platform\service\plugin\ModulePackagePreflight;
use PeanutAdmin\Kernel\Module\ModuleHostLayout;
use PeanutAdmin\Kernel\Module\ModuleKey;

/** Generates the one key-derived backend/frontend Module skeleton. */
final class ModuleScaffoldGenerator
{
    private const BACKEND_FILES = [
        'module.json' => 'backend/module.json.stub',
        'ModuleProvider.php' => 'backend/ModuleProvider.php.stub',
        'Http/routes.php' => 'backend/Http/routes.php.stub',
        'Http/Controller/.gitkeep' => null,
        'Service/.gitkeep' => null,
        'Model/.gitkeep' => null,
        'Resources/permissions.json' => 'backend/empty-array.json.stub',
        'Resources/menus.json' => 'backend/empty-array.json.stub',
        'Resources/setting-definitions.json' => 'backend/empty-array.json.stub',
        'Database/Migrations/.gitkeep' => null,
        'composer.json' => 'backend/composer.json.stub',
    ];

    private const FRONTEND_FILES = [
        'contribution.ts' => 'frontend/contribution.ts.stub',
        'views/.gitkeep' => null,
        'api.ts' => 'frontend/api.ts.stub',
        'package.json' => 'frontend/package.json.stub',
    ];

    private ModuleHostLayout $layout;
    private string $templateRoot;

    public function __construct(
        private string $projectRoot,
        ?string $templateRoot = null,
        private ?string $composerBinary = null,
    ) {
        $root = realpath($projectRoot);
        if ($root === false || !is_dir($root)) {
            throw new ModuleScaffoldException('MODULE_CREATE_PROJECT_UNAVAILABLE', 'Module project root is unavailable.');
        }
        $this->projectRoot = $root;
        $templateRoot ??= $root . '/server/resources/module-scaffold';
        $resolvedTemplates = realpath($templateRoot);
        if ($resolvedTemplates === false || !is_dir($resolvedTemplates)) {
            throw new ModuleScaffoldException('MODULE_CREATE_TEMPLATE_INVALID', 'Module scaffold template is unavailable.');
        }
        $this->templateRoot = $resolvedTemplates;
        $this->layout = new ModuleHostLayout('server/app/Modules', 'app\\Modules', 'web/src/modules');
    }

    /**
     * @return array{
     *   operation:string,module_key:string,vendor:string,backend_path:string,
     *   frontend_path:string,frontend_entry:string,php_package:string,web_package:string
     * }
     */
    public function create(string $moduleKey, ?string $vendor = null): array
    {
        $moduleKey = trim($moduleKey);
        try {
            $key = ModuleKey::fromString($moduleKey);
        } catch (\InvalidArgumentException $exception) {
            throw new ModuleScaffoldException('MODULE_CREATE_KEY_INVALID', 'Module key is invalid.', 0, $exception);
        }

        $pascalSegments = $key->pascalSegments();
        $derivedVendor = $pascalSegments[0];
        $vendor = trim((string)$vendor);
        if ($vendor !== '' && preg_match('/^[A-Z][A-Za-z0-9]*$/D', $vendor) !== 1) {
            throw new ModuleScaffoldException('MODULE_CREATE_VENDOR_INVALID', 'Module vendor is invalid.');
        }
        if ($vendor !== '' && $vendor !== $derivedVendor) {
            throw new ModuleScaffoldException(
                'MODULE_CREATE_VENDOR_MISMATCH',
                'Module vendor must equal the namespace derived from the Module key.',
            );
        }
        $vendor = $derivedVendor;

        $backendRelative = rtrim($this->layout->backendRelativePath($key), '/');
        $frontendRelative = rtrim($this->layout->frontendRelativePath($key), '/');
        $frontendEntry = $frontendRelative . '/contribution.ts';
        $backendRoot = $this->projectRoot . '/' . $backendRelative;
        $frontendRoot = $this->projectRoot . '/' . $frontendRelative;
        $backendBase = $this->projectRoot . '/server/app/Modules';
        $frontendBase = $this->projectRoot . '/web/src/modules';
        $this->assertAvailableTarget($backendBase, $backendRoot);
        $this->assertAvailableTarget($frontendBase, $frontendRoot);

        $rawSegments = explode('.', $key->value());
        $vendorKey = $rawSegments[0];
        $slug = $key->slug();
        $namespace = rtrim($this->layout->backendNamespace($key), '\\');
        $vendorNamespace = 'app\\Modules\\' . $vendor;
        $displayName = implode(' ', array_map(
            static fn(string $segment): string => implode(' ', array_map('ucfirst', explode('-', $segment))),
            $rawSegments,
        ));
        $description = "Generated {$moduleKey} Module skeleton.";
        $composerVendor = $vendorKey === 'official' ? 'peanut-business' : $vendorKey;
        $npmScope = $vendorKey === 'official' ? 'peanut-admin' : $vendorKey;
        $phpPackage = $composerVendor . '/' . $slug;
        $webPackage = '@' . $npmScope . '/' . $slug;
        $replacements = [
            '${MODULE_KEY}' => $moduleKey,
            '${VENDOR}' => $vendor,
            '${MODULE}' => $pascalSegments[array_key_last($pascalSegments)],
            '${VENDOR_NAMESPACE}' => $vendorNamespace,
            '${FRONTEND_SLUG}' => $slug,
            '${FRONTEND_ENTRY}' => $frontendEntry,
            '${PHP_NAMESPACE}' => $namespace,
            '${MODULE_KEY_JSON}' => $this->jsonString($moduleKey),
            '${NAME_JSON}' => $this->jsonString($displayName),
            '${DESCRIPTION_JSON}' => $this->jsonString($description),
            '${PHP_PACKAGE_JSON}' => $this->jsonString($phpPackage),
            '${WEB_PACKAGE_JSON}' => $this->jsonString($webPackage),
            '${BACKEND_PROVIDER_JSON}' => $this->jsonString($namespace . '\\ModuleProvider'),
            '${FRONTEND_ENTRY_JSON}' => $this->jsonString($frontendEntry),
            '${AUTOLOAD_NAMESPACE_JSON}' => $this->jsonString($namespace . '\\'),
        ];

        $backendCreated = false;
        $frontendCreated = false;
        try {
            if (!mkdir($backendRoot, 0755, true)) {
                throw new ModuleScaffoldException('MODULE_CREATE_WRITE_FAILED', 'Module backend root cannot be created.');
            }
            $backendCreated = true;
            if (!mkdir($frontendRoot, 0755, true)) {
                throw new ModuleScaffoldException('MODULE_CREATE_WRITE_FAILED', 'Module frontend root cannot be created.');
            }
            $frontendCreated = true;
            $this->writeFiles($backendRoot, self::BACKEND_FILES, $replacements);
            $this->writeFiles($frontendRoot, self::FRONTEND_FILES, $replacements);
            $this->postflight($moduleKey, $backendRoot);
        } catch (ModuleScaffoldException $exception) {
            if ($frontendCreated) $this->removeCreatedTree($frontendRoot, $frontendBase);
            if ($backendCreated) $this->removeCreatedTree($backendRoot, $backendBase);
            throw $exception;
        } catch (\Throwable $exception) {
            if ($frontendCreated) $this->removeCreatedTree($frontendRoot, $frontendBase);
            if ($backendCreated) $this->removeCreatedTree($backendRoot, $backendBase);
            throw new ModuleScaffoldException('MODULE_CREATE_FAILED', 'Module scaffold generation failed.', 0, $exception);
        }

        return [
            'operation' => 'created',
            'module_key' => $moduleKey,
            'vendor' => $vendor,
            'backend_path' => $backendRelative,
            'frontend_path' => $frontendRelative,
            'frontend_entry' => $frontendEntry,
            'php_package' => $phpPackage,
            'web_package' => $webPackage,
        ];
    }

    private function assertAvailableTarget(string $base, string $target): void
    {
        $resolvedBase = realpath($base);
        if ($resolvedBase === false || $resolvedBase !== $base || !str_starts_with($target, $base . '/')) {
            throw new ModuleScaffoldException('MODULE_CREATE_PROJECT_UNAVAILABLE', 'Module source root is unavailable.');
        }
        $relative = substr(dirname($target), strlen($base) + 1);
        $cursor = $base;
        foreach (array_filter(explode('/', $relative), 'strlen') as $segment) {
            $cursor .= '/' . $segment;
            if (is_link($cursor) || (file_exists($cursor) && !is_dir($cursor))) {
                throw new ModuleScaffoldException('MODULE_CREATE_PATH_INVALID', 'Module target path contains an unsafe ancestor.');
            }
        }
        if (file_exists($target) || is_link($target)) {
            throw new ModuleScaffoldException('MODULE_CREATE_TARGET_EXISTS', 'Module target already exists.');
        }
    }

    /** @param array<string,?string> $files @param array<string,string> $replacements */
    private function writeFiles(string $targetRoot, array $files, array $replacements): void
    {
        foreach ($files as $relative => $template) {
            $path = $targetRoot . '/' . $relative;
            $directory = dirname($path);
            if (!is_dir($directory) && !mkdir($directory, 0755, true)) {
                throw new ModuleScaffoldException('MODULE_CREATE_WRITE_FAILED', 'Module scaffold directory cannot be created.');
            }
            $contents = '';
            if ($template !== null) {
                $templatePath = $this->templateRoot . '/' . $template;
                if (!is_file($templatePath) || is_link($templatePath)) {
                    throw new ModuleScaffoldException('MODULE_CREATE_TEMPLATE_INVALID', 'Module scaffold template is invalid.');
                }
                $templateContents = file_get_contents($templatePath);
                if (!is_string($templateContents)) {
                    throw new ModuleScaffoldException('MODULE_CREATE_TEMPLATE_INVALID', 'Module scaffold template cannot be read.');
                }
                $contents = strtr($templateContents, $replacements);
                if (preg_match('/\$\{[A-Z][A-Z0-9_]*\}/', $contents) === 1) {
                    throw new ModuleScaffoldException('MODULE_CREATE_TEMPLATE_INVALID', 'Module scaffold template has an unknown variable.');
                }
            }
            $written = file_put_contents($path, $contents, LOCK_EX);
            if ($written === false || $written !== strlen($contents)) {
                throw new ModuleScaffoldException('MODULE_CREATE_WRITE_FAILED', 'Module scaffold file cannot be written.');
            }
        }
    }

    private function postflight(string $moduleKey, string $backendRoot): void
    {
        foreach (['module.json', 'composer.json', 'Http/routes.php', 'Resources/permissions.json',
            'Resources/menus.json', 'Resources/setting-definitions.json', 'Database/Migrations'] as $relative) {
            if (!file_exists($backendRoot . '/' . $relative)) {
                throw new ModuleScaffoldException('MODULE_CREATE_POSTCHECK_FAILED', 'Generated Module backend is incomplete.');
            }
        }
        try {
            $inspection = (new ModulePackagePreflight($this->projectRoot))->inspect($moduleKey);
        } catch (\Throwable $exception) {
            throw new ModuleScaffoldException('MODULE_CREATE_POSTCHECK_FAILED', 'Generated Module manifest preflight failed.', 0, $exception);
        }
        if ($inspection['backend_relative'] !== rtrim($this->layout->backendRelativePath(ModuleKey::fromString($moduleKey)), '/')
            || $inspection['frontend_relative'] !== rtrim($this->layout->frontendRelativePath(ModuleKey::fromString($moduleKey)), '/')) {
            throw new ModuleScaffoldException('MODULE_CREATE_POSTCHECK_FAILED', 'Generated Module path differs from its key.');
        }
        $this->validateComposer($backendRoot);
    }

    private function validateComposer(string $backendRoot): void
    {
        $binary = trim($this->composerBinary ?? (string)(getenv('COMPOSER_BINARY') ?: 'composer'));
        if ($binary === '' || str_contains($binary, "\0")) {
            throw new ModuleScaffoldException('MODULE_CREATE_COMPOSER_INVALID', 'Composer validator is unavailable.');
        }
        $pipes = [];
        $process = @proc_open(
            [$binary, 'validate', '--no-check-publish', '--no-interaction', '--working-dir=' . $backendRoot],
            [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
        );
        if (!is_resource($process)) {
            throw new ModuleScaffoldException('MODULE_CREATE_COMPOSER_INVALID', 'Composer validator is unavailable.');
        }
        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        if (proc_close($process) !== 0) {
            throw new ModuleScaffoldException(
                'MODULE_CREATE_COMPOSER_INVALID',
                'Generated Composer manifest is invalid: ' . trim((string)$stdout . "\n" . (string)$stderr),
            );
        }
    }

    private function jsonString(string $value): string
    {
        return json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
    }

    private function removeCreatedTree(string $path, string $base): void
    {
        if (is_dir($path) && !is_link($path)) {
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS),
                \RecursiveIteratorIterator::CHILD_FIRST,
            );
            foreach ($iterator as $entry) {
                $entry->isDir() && !$entry->isLink()
                    ? rmdir($entry->getPathname())
                    : unlink($entry->getPathname());
            }
            rmdir($path);
        }
        $cursor = dirname($path);
        while ($cursor !== $base && str_starts_with($cursor, $base . '/')) {
            if (!is_dir($cursor) || is_link($cursor) || (scandir($cursor) ?: []) !== ['.', '..']) break;
            rmdir($cursor);
            $cursor = dirname($cursor);
        }
    }
}
