<?php
declare(strict_types=1);

namespace app\adminapi\service;

use PDO;
use PeanutAdmin\Kernel\Auth\TenantContext;
use PeanutAdmin\Kernel\Authorization\PdoTenantAuthorizationRepository;
use PeanutAdmin\Kernel\Menu\MenuDefinition;
use PeanutAdmin\Kernel\Menu\MenuRegistry;
use PeanutAdmin\Kernel\Menu\PdoMenuCatalogRepository;
use think\facade\Db;

/**
 * Adapts the Core Module/TenantModule catalog to the legacy Admin menu payload.
 *
 * The bridge is read-only: Plugin installation owns deployment state, the platform
 * owns TenantModule enablement, and Core RBAC owns member Permission grants.
 */
final readonly class CoreTenantModuleAdminBridge
{
    public function __construct(private ?PDO $pdo = null)
    {
    }

    /** @return array{menu:list<array<string,mixed>>,permissions:list<string>} */
    public function accessData(mixed $tenantContext): array
    {
        if (!$tenantContext instanceof TenantContext
            || $tenantContext->tenantId < 1
            || $tenantContext->memberId < 1) {
            return ['menu' => [], 'permissions' => []];
        }

        try {
            $pdo = $this->connection();
            $permissions = (new PdoTenantAuthorizationRepository($pdo))->permissions(
                $tenantContext->tenantId,
                $tenantContext->memberId
            )->keys();
            $catalog = new PdoMenuCatalogRepository($pdo);
            $definitions = $catalog->activeDefinitions('tenant');
            $deploymentModules = $catalog->activeDeploymentModules();
            $tenantModules = $catalog->activeTenantModules($tenantContext->tenantId);
            $visible = (new MenuRegistry($definitions))->visible(
                'admin-web',
                static fn(string $moduleKey): bool => in_array($moduleKey, $deploymentModules, true),
                static fn(string $moduleKey): bool => in_array($moduleKey, $tenantModules, true),
                static fn(string $permission): bool => in_array($permission, $permissions, true)
            );

            return [
                'menu' => $this->serverMenuRecords($visible),
                'permissions' => array_values(array_unique($permissions)),
            ];
        } catch (\Throwable) {
            // Existing installations without the lifecycle migration keep their
            // legacy Admin menu. Module routes remain absent and therefore closed.
            return ['menu' => [], 'permissions' => []];
        }
    }

    private function connection(): PDO
    {
        if ($this->pdo instanceof PDO) {
            return $this->pdo;
        }
        $connection = Db::connect()->connect();
        if (!$connection instanceof PDO) {
            throw new \RuntimeException('CORE_TENANT_MODULE_DATABASE_UNAVAILABLE');
        }
        return $connection;
    }

    /**
     * @param list<MenuDefinition> $definitions
     * @return list<array<string,mixed>>
     */
    private function serverMenuRecords(array $definitions): array
    {
        $ids = [];
        foreach ($definitions as $definition) {
            $ids[$definition->key] = 2_000_000_000
                + (int)sprintf('%u', crc32($definition->key)) % 100_000_000;
        }

        $records = [];
        foreach ($definitions as $definition) {
            // Legacy ServerMenuRecord groups have a concrete client route. Core
            // groups do not, so page/link records are flattened for this adapter.
            if ($definition->type === 'group' || $definition->routePath === null) {
                continue;
            }
            $records[] = [
                'id' => $ids[$definition->key],
                'pid' => 0,
                'type' => 'C',
                'name' => $definition->name,
                'icon' => $definition->icon ?? '',
                'sort' => $definition->sortOrder,
                'perms' => $definition->requiredPermission ?? '',
                'paths' => $definition->routePath,
                'component' => $definition->componentKey ?? '',
                'is_cache' => 0,
                'is_show' => 1,
                'is_disable' => 0,
                'module_key' => $definition->moduleKey,
                'required_permission' => $definition->requiredPermission,
                'children' => [],
            ];
        }

        return $records;
    }
}
