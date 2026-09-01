<?php
declare(strict_types=1);

namespace app\common\service\authorization;

use app\common\model\auth\SystemMenu;
use app\platform\service\module\PdoModuleGovernanceProvider;
use PDO;
use PeanutAdmin\Kernel\Auth\TenantContext;
use PeanutAdmin\Kernel\Authorization\PdoTenantAuthorizationRepository;
use PeanutAdmin\Kernel\Menu\MenuDefinition;
use PeanutAdmin\Kernel\Menu\MenuRegistry;
use PeanutAdmin\Kernel\Menu\PdoMenuCatalogRepository;

/**
 * Adapts the Core Module/TenantModule catalog to the Admin Shell menu payload.
 *
 * The bridge is read-only: Plugin installation owns deployment state, the platform
 * owns TenantModule enablement, and Core RBAC owns member Permission grants.
 */
final readonly class CoreTenantModuleAdminBridge
{
    private const APPLICATION_PERMISSION_OWNER = 'peanut.admin';

    /** @return list<string> */
    public static function officialModuleMenuPaths(): array
    {
        return [
            '/system/file',
            '/system/crontab',
            '/notice/channel',
            '/notice/template',
            '/notice/log',
            '/app-setting/channel',
            '/app-setting/pay',
            '/member/list',
            '/member/tag',
            '/finance/account-log',
            '/finance/recharge',
            '/finance/refund',
        ];
    }

    public function __construct(
        private PDO $pdo,
        private PdoModuleGovernanceProvider $moduleGovernance,
    ) {
    }

    /** @return array{menu:list<array<string,mixed>>,permissions:list<string>} */
    public function accessData(mixed $tenantContext): array
    {
        if (!$tenantContext instanceof TenantContext
            || $tenantContext->tenantId < 1
            || $tenantContext->memberId < 1) {
            return ['menu' => [], 'permissions' => []];
        }

        $pdo = $this->pdo;
        $permissions = array_values(array_unique([
            ...(new PdoTenantAuthorizationRepository($pdo))->permissions(
                $tenantContext->tenantId,
                $tenantContext->memberId
            )->keys(),
            ...$this->applicationPermissions($pdo, $tenantContext),
        ]));
        if ($this->isTenantOwner($pdo, $tenantContext)) {
            $permissions = array_values(array_unique([
                ...$permissions,
                ...$this->registeredPermissions($tenantContext->tenantId),
            ]));
        }
        $catalog = new PdoMenuCatalogRepository($pdo);
        $definitions = $catalog->activeDefinitions('tenant');
        $qualification = $this->moduleGovernance->qualification();
        $deploymentModules = array_map(
            static fn($module): string => $module->moduleKey,
            $qualification->installedModules()
        );
        $tenantModules = $qualification->activeTenantModuleKeys($tenantContext->tenantId);
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
        $pdo = $this->pdo;
        $catalog = new PdoMenuCatalogRepository($pdo);
        $qualification = $this->moduleGovernance->qualification();
        $deploymentModules = array_map(
            static fn($module): string => $module->moduleKey,
            $qualification->installedModules()
        );
        $tenantModules = $qualification->activeTenantModuleKeys($tenantId);
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
        $pdo = $this->pdo;
        $qualification = $this->moduleGovernance->qualification();
        $installed = array_fill_keys(array_map(
            static fn($module): string => $module->moduleKey,
            $qualification->installedModules()
        ), true);
        $active = array_values(array_unique([
            self::APPLICATION_PERMISSION_OWNER,
            ...array_filter(
                $qualification->activeTenantModuleKeys($tenantId),
                static fn(string $moduleKey): bool => isset($installed[$moduleKey])
            ),
        ]));
        $placeholders = implode(',', array_map(
            static fn(int $index): string => ':module_' . $index,
            array_keys($active)
        ));
        $statement = $pdo->prepare(
            "SELECT DISTINCT p.`key` FROM pa_permission p WHERE p.status = 'active' "
            . "AND p.module_key IN ({$placeholders}) ORDER BY p.`key`"
        );
        $parameters = [];
        foreach ($active as $index => $moduleKey) {
            $parameters['module_' . $index] = $moduleKey;
        }
        $statement->execute($parameters);
        return array_values(array_map('strval', $statement->fetchAll(PDO::FETCH_COLUMN)));
    }

    /** @return list<string> */
    public function registeredSystemMenuPermissions(int $tenantId): array
    {
        if ($tenantId < 1) {
            return [];
        }
        $pdo = $this->pdo;
        $qualification = $this->moduleGovernance->qualification();
        $installed = array_fill_keys(array_map(
            static fn($module): string => $module->moduleKey,
            $qualification->installedModules()
        ), true);
        $active = array_fill_keys(array_values(array_unique([
            self::APPLICATION_PERMISSION_OWNER,
            ...array_filter(
                $qualification->activeTenantModuleKeys($tenantId),
                static fn(string $moduleKey): bool => isset($installed[$moduleKey])
            ),
        ])), true);
        $rows = SystemMenu::alias('m')
            ->join('permission p', 'p.`key`=m.perms', 'LEFT')
            ->where('m.is_disable', 0)
            ->where('m.perms', '<>', '')
            ->field(['m.perms', 'p.module_key', 'p.status' => 'permission_status'])
            ->distinct(true)
            ->select()
            ->toArray();

        $permissions = [];
        foreach ($rows as $row) {
            $moduleKey = $row['module_key'] ?? null;
            if ($moduleKey !== null && $moduleKey !== '') {
                if (($row['permission_status'] ?? null) !== 'active' || !isset($active[$moduleKey])) {
                    continue;
                }
            }
            $permissions[] = (string)$row['perms'];
        }

        return array_values(array_unique($permissions));
    }

    /** @return list<string> */
    private function applicationPermissions(PDO $pdo, TenantContext $context): array
    {
        $statement = $pdo->prepare(<<<'SQL'
SELECT DISTINCT permission.`key`
FROM pa_tenant tenant
JOIN pa_tenant_member member
  ON member.tenant_id = tenant.id
 AND member.id = :member_id
 AND member.status = 'active'
JOIN pa_member_role member_role
  ON member_role.tenant_id = tenant.id
 AND member_role.tenant_member_id = member.id
JOIN pa_role role
  ON role.tenant_id = tenant.id
 AND role.id = member_role.role_id
 AND role.status = 'active'
JOIN pa_role_permission role_permission
  ON role_permission.tenant_id = tenant.id
 AND role_permission.role_id = role.id
JOIN pa_permission permission
  ON permission.id = role_permission.permission_id
 AND permission.module_key = 'peanut.admin'
 AND permission.status = 'active'
WHERE tenant.id = :tenant_id AND tenant.status = 'active'
ORDER BY permission.`key`
SQL);
        $statement->execute([
            'tenant_id' => $context->tenantId,
            'member_id' => $context->memberId,
        ]);

        return array_values(array_map('strval', $statement->fetchAll(PDO::FETCH_COLUMN)));
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
