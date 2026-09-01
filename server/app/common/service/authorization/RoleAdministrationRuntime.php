<?php
declare(strict_types=1);

namespace app\common\service\authorization;

use PDO;
use app\common\model\auth\SystemMenu;
use PeanutAdmin\Kernel\Auth\TenantContext;
use PeanutAdmin\Kernel\Authorization\Application\RoleAdminService;

/** Container-owned assembly and read projections for native Tenant roles. */
final readonly class RoleAdministrationRuntime
{
    public function __construct(
        private PDO $pdo,
        private RoleAdminService $roles,
        private AdminAuthorizationService $authorization,
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
        $ids = array_map('intval', SystemMenu::where('is_disable', 0)
            ->whereIn('perms', $permissionKeys)
            ->order('id')
            ->column('id'));
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
        $keys = array_values(array_unique(array_map('strval', SystemMenu::alias('m')
            ->join('permission p', 'p.`key`=m.perms')
            ->where('m.is_disable', 0)
            ->whereIn('m.id', $menuIds)
            ->where('m.perms', '<>', '')
            ->where('p.status', 'active')
            ->order('p.`key`')
            ->column('p.`key`'))));
        $selected = array_fill_keys($menuIds, true);
        foreach ($this->authorization->assignableMenuRecordsForTenant($tenantId) as $menu) {
            if (isset($selected[(int)$menu['id']]) && trim((string)$menu['required_permission']) !== '') {
                $keys[] = (string)$menu['required_permission'];
            }
        }
        return array_values(array_unique($keys));
    }
}
