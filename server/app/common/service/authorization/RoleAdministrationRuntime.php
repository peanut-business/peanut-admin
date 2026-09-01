<?php
declare(strict_types=1);

namespace app\common\service\authorization;

use app\common\contract\authorization\AdminMenuPersistence;
use PDO;
use PeanutAdmin\Kernel\Auth\TenantContext;
use PeanutAdmin\Kernel\Authorization\Application\RoleAdminService;

/** Container-owned assembly and read projections for native Tenant roles. */
final readonly class RoleAdministrationRuntime
{
    public function __construct(
        private PDO $pdo,
        private RoleAdminService $roles,
        private AdminAuthorizationService $authorization,
        private AdminMenuPersistence $menus,
    ) {
    }

    public function service(): RoleAdminService
    {
        return $this->roles;
    }

    /** @param list<string> $permissionKeys @return list<int> */
    public function menuIds(TenantContext $context, array $permissionKeys): array
    {
        if ($permissionKeys === []) {
            return [];
        }
        $ids = $this->menus->enabledMenuIds($permissionKeys);
        foreach ($this->authorization->assignableMenuRecords($context) as $menu) {
            if (in_array((string)$menu['required_permission'], $permissionKeys, true)) {
                $ids[] = (int)$menu['id'];
            }
        }
        return array_values(array_unique($ids));
    }

    public function memberCount(int $tenantId, int $roleId): int
    {
        $statement = $this->pdo->prepare(
            'SELECT COUNT(*) FROM pa_member_role WHERE tenant_id=? AND role_id=?'
        );
        $statement->execute([$tenantId, $roleId]);
        return (int)$statement->fetchColumn();
    }

    /** @param list<int> $menuIds @return list<string> */
    public function permissionKeys(int $tenantId, array $menuIds): array
    {
        if ($menuIds === []) {
            return [];
        }
        $keys = $this->menus->activePermissionKeys($menuIds);
        $selected = array_fill_keys($menuIds, true);
        foreach ($this->authorization->assignableMenuRecordsForTenant($tenantId) as $menu) {
            if (isset($selected[(int)$menu['id']]) && trim((string)$menu['required_permission']) !== '') {
                $keys[] = (string)$menu['required_permission'];
            }
        }
        return array_values(array_unique($keys));
    }
}
