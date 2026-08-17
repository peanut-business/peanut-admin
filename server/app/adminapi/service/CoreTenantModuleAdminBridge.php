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
 * Adapts the Core Module/TenantModule catalog to the Admin Shell menu payload.
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

        $pdo = $this->connection();
        $permissions = (new PdoTenantAuthorizationRepository($pdo))->permissions(
            $tenantContext->tenantId,
            $tenantContext->memberId
        )->keys();
        if ($this->isTenantOwner($pdo, $tenantContext)) {
            $permissions = array_values(array_unique([
                ...$permissions,
                ...$this->registeredPermissions($tenantContext->tenantId),
            ]));
        }
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
    }

    /** @return list<array<string,mixed>> */
    public function assignableMenuRecords(int $tenantId): array
    {
        if ($tenantId < 1) {
            return [];
        }
        $pdo = $this->connection();
        $catalog = new PdoMenuCatalogRepository($pdo);
        $deploymentModules = $catalog->activeDeploymentModules();
        $tenantModules = $catalog->activeTenantModules($tenantId);
        $visible = (new MenuRegistry($catalog->activeDefinitions('tenant')))->visible(
            'admin-web',
            static fn(string $moduleKey): bool => in_array($moduleKey, $deploymentModules, true),
            static fn(string $moduleKey): bool => in_array($moduleKey, $tenantModules, true),
            static fn(string $_permission): bool => true
        );

        return $this->serverMenuRecords($visible);
    }

    /** @return list<string> */
    public function registeredPermissions(int $tenantId): array
    {
        if ($tenantId < 1) {
            return [];
        }
        $statement = $this->connection()->prepare(<<<'SQL'
SELECT DISTINCT p.`key`
FROM pa_permission p
JOIN pa_module_installation installation
  ON installation.module_key = p.module_key AND installation.status = 'active'
JOIN pa_tenant_module tenant_module
  ON tenant_module.module_key = p.module_key
 AND tenant_module.tenant_id = :tenant_id
 AND tenant_module.status = 'enabled'
WHERE p.status = 'active'
  AND (tenant_module.effective_at IS NULL OR tenant_module.effective_at <= CURRENT_TIMESTAMP(3))
  AND (tenant_module.expires_at IS NULL OR tenant_module.expires_at > CURRENT_TIMESTAMP(3))
ORDER BY p.`key`
SQL);
        $statement->execute(['tenant_id' => $tenantId]);
        return array_values(array_map('strval', $statement->fetchAll(PDO::FETCH_COLUMN)));
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
            $ids[$definition->key] = self::virtualMenuId($definition->key);
        }

        $records = [];
        foreach ($definitions as $definition) {
            // Admin Shell groups require a concrete client route. Core groups do
            // not, so page/link records are flattened for this adapter.
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

    public static function virtualMenuId(string $menuKey): int
    {
        return 2_000_000_000 + (int)sprintf('%u', crc32($menuKey)) % 100_000_000;
    }

    private function isTenantOwner(PDO $pdo, TenantContext $context): bool
    {
        $statement = $pdo->prepare(<<<'SQL'
SELECT 1
FROM pa_member_role mr
JOIN pa_role r
  ON r.tenant_id = mr.tenant_id
 AND r.id = mr.role_id
 AND r.`key` = 'core.tenant-owner'
 AND r.is_builtin = 1
 AND r.status = 'active'
WHERE mr.tenant_id = :tenant_id
  AND mr.tenant_member_id = :member_id
LIMIT 1
SQL);
        $statement->execute([
            'tenant_id' => $context->tenantId,
            'member_id' => $context->memberId,
        ]);

        return $statement->fetchColumn() !== false;
    }
}
