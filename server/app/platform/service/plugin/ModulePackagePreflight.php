<?php
declare(strict_types=1);

namespace app\platform\service\plugin;

use app\platform\service\module\OpisManifestSchemaValidator;
use app\platform\service\module\StrictVersionConstraintMatcher;
use PeanutAdmin\Kernel\Module\ManifestDocument;
use PeanutAdmin\Kernel\Module\ManifestLoader;
use PeanutAdmin\Kernel\Module\ModuleHostLayout;
use PeanutAdmin\Kernel\Module\ModuleKey;

/** Validates package Module identity and all values derived from module.json. */
final readonly class ModulePackagePreflight
{
    private ModuleHostLayout $layout;

    public function __construct(private string $projectRoot)
    {
        $this->layout = new ModuleHostLayout('server/app/Modules', 'app\\Modules', 'web/src/modules');
    }

    /**
     * @return array{
     *   key:string,version:string,backend_relative:string,frontend_relative:?string,
     *   manifest:ManifestDocument,dependencies:list<array{module_key:string,version:string}>,owned_tables:list<string>
     * }
     */
    public function inspect(string $moduleKey): array
    {
        try {
            $key = ModuleKey::fromString($moduleKey);
        } catch (\InvalidArgumentException $exception) {
            throw new PluginPackageException('MODULE_PACKAGE_MANIFEST_INVALID', 'Module key is invalid.', 0, $exception);
        }
        $backendRelative = rtrim($this->layout->backendRelativePath($key), '/');
        $backendRoot = $this->projectRoot . '/' . $backendRelative;
        try {
            $manifest = (new ManifestLoader())->load($backendRoot);
            (new OpisManifestSchemaValidator($this->moduleSchema()))->assertValid($manifest->object);
        } catch (\Throwable $exception) {
            throw new PluginPackageException('MODULE_PACKAGE_MANIFEST_INVALID', 'Module manifest validation failed.', 0, $exception);
        }
        if (($manifest->data['key'] ?? null) !== $moduleKey) {
            throw new PluginPackageException('MODULE_PACKAGE_PATH_MISMATCH', 'Module key does not match its derived backend path.');
        }

        $frontend = is_array($manifest->data['frontend'] ?? null) ? $manifest->data['frontend'] : [];
        $entry = $frontend['entry'] ?? null;
        $frontendRelative = null;
        if ($entry !== null) {
            $expectedEntry = $this->layout->frontendRelativePath($key) . 'contribution.ts';
            if (!is_string($entry) || $entry !== $expectedEntry) {
                throw new PluginPackageException(
                    'MODULE_PACKAGE_FRONTEND_ENTRY_MISMATCH',
                    'Module frontend.entry differs from the path derived from its key.'
                );
            }
            if (!is_file($this->projectRoot . '/' . $expectedEntry)) {
                throw new PluginPackageException('MODULE_PACKAGE_FRONTEND_ENTRY_MISSING', 'Module frontend entry is missing.');
            }
            $frontendRelative = rtrim($this->layout->frontendRelativePath($key), '/');
        }

        $catalog = is_array($manifest->data['catalog'] ?? null) ? $manifest->data['catalog'] : [];
        $permissions = [];
        foreach ($catalog['permissions'] ?? [] as $permission) {
            $permissionKey = is_array($permission) ? ($permission['key'] ?? null) : null;
            if (!is_string($permissionKey) || !str_starts_with($permissionKey, $moduleKey . '.')) {
                throw new PluginPackageException(
                    'MODULE_PACKAGE_PERMISSION_NOT_NAMESPACED',
                    'Every Module permission key must use its Module key namespace.'
                );
            }
            $permissions[$permissionKey] = true;
        }
        foreach ($catalog['menus'] ?? [] as $menu) {
            $required = is_array($menu) ? ($menu['required_permission'] ?? null) : null;
            if ($required !== null && (!is_string($required) || !isset($permissions[$required]))) {
                throw new PluginPackageException(
                    'MODULE_PACKAGE_PERMISSION_REFERENCE_INVALID',
                    'Module menu permission must reference a declared namespaced permission.'
                );
            }
        }

        $dependencies = [];
        foreach ($manifest->data['dependencies'] ?? [] as $dependency) {
            if (!is_array($dependency) || !is_string($dependency['module_key'] ?? null)
                || !is_string($dependency['version'] ?? null)) {
                throw new PluginPackageException('MODULE_PACKAGE_DEPENDENCY_INVALID', 'Module dependency is invalid.');
            }
            $dependencies[] = [
                'module_key' => $dependency['module_key'],
                'version' => $dependency['version'],
            ];
        }

        return [
            'key' => $moduleKey,
            'version' => (string)$manifest->data['version'],
            'backend_relative' => $backendRelative,
            'frontend_relative' => $frontendRelative,
            'manifest' => $manifest,
            'dependencies' => $dependencies,
            'owned_tables' => array_values((array)($manifest->data['database']['owned_tables'] ?? [])),
        ];
    }

    /**
     * @param array<string,array{version:string,dependencies:list<array{module_key:string,version:string}>}> $modules
     * @param array<string,string> $availableVersions
     * @return list<string>
     */
    public function dependencyOrder(array $modules, array $availableVersions): array
    {
        $matcher = new StrictVersionConstraintMatcher();
        $visiting = [];
        $visited = [];
        $ordered = [];
        $visit = function (string $key) use (&$visit, &$visiting, &$visited, &$ordered, $modules, $availableVersions, $matcher): void {
            if (isset($visited[$key])) {
                return;
            }
            if (isset($visiting[$key])) {
                throw new PluginPackageException('MODULE_PACKAGE_DEPENDENCY_CYCLE', 'Module package dependency cycle detected.');
            }
            $visiting[$key] = true;
            foreach ($modules[$key]['dependencies'] as $dependency) {
                $dependencyKey = $dependency['module_key'];
                $version = $modules[$dependencyKey]['version'] ?? $availableVersions[$dependencyKey] ?? null;
                if (!is_string($version)) {
                    throw new PluginPackageException('MODULE_PACKAGE_DEPENDENCY_MISSING', 'Module package dependency is missing.');
                }
                if (!$matcher->matches($version, $dependency['version'])) {
                    throw new PluginPackageException('MODULE_PACKAGE_DEPENDENCY_INCOMPATIBLE', 'Module package dependency version is incompatible.');
                }
                if (isset($modules[$dependencyKey])) {
                    $visit($dependencyKey);
                }
            }
            unset($visiting[$key]);
            $visited[$key] = true;
            $ordered[] = $key;
        };
        $keys = array_keys($modules);
        sort($keys, SORT_STRING);
        foreach ($keys as $key) {
            $visit($key);
        }
        return $ordered;
    }

    private function moduleSchema(): string
    {
        $kernelRoot = dirname((new \ReflectionClass(\PeanutAdmin\Kernel\Module\ModuleProvider::class))->getFileName(), 3);
        return $kernelRoot . '/resources/schemas/module-manifest.schema.json';
    }
}
