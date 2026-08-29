<?php
declare(strict_types=1);

namespace app\common\service\authorization;

use PDO;
use PeanutAdmin\Kernel\Auth\TenantContext;
use PeanutAdmin\Kernel\Authorization\Application\RoleAdminService;

/** Container-owned assembly and read projections for native Tenant roles. */
final readonly class RoleAdministrationRuntime
{
    public function __construct(private PDO $pdo)
    {
    }

    public function service(): RoleAdminService
    {
        return new RoleAdminService($this->pdo);
    }

    /** @param list<string> $permissionKeys @return list<int> */
    public function menuIds(TenantContext $context, array $permissionKeys): array
    {
        if ($permissionKeys === []) {
            return [];
        }
        $placeholders = implode(',', array_fill(0, count($permissionKeys), '?'));
        $statement = $this->pdo->prepare(
            "SELECT id FROM pa_system_menu WHERE is_disable=0 AND perms IN ({$placeholders}) ORDER BY id"
        );
        $statement->execute($permissionKeys);
        $ids = array_map('intval', $statement->fetchAll(PDO::FETCH_COLUMN));
        foreach ((new AdminAuthorizationService($this->pdo))->assignableMenuRecords($context) as $menu) {
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
        $placeholders = implode(',', array_fill(0, count($menuIds), '?'));
        $statement = $this->pdo->prepare(
            "SELECT DISTINCT p.`key` FROM pa_system_menu m "
            . "JOIN pa_permission p ON p.`key`=m.perms AND p.status='active' "
            . "WHERE m.id IN ({$placeholders}) AND m.is_disable=0 AND m.perms<>'' ORDER BY p.`key`"
        );
        $statement->execute($menuIds);
        $keys = array_values(array_map('strval', $statement->fetchAll(PDO::FETCH_COLUMN)));
        $selected = array_fill_keys($menuIds, true);
        foreach ((new AdminAuthorizationService($this->pdo))->assignableMenuRecordsForTenant($tenantId) as $menu) {
            if (isset($selected[(int)$menu['id']]) && trim((string)$menu['required_permission']) !== '') {
                $keys[] = (string)$menu['required_permission'];
            }
        }
        return array_values(array_unique($keys));
    }
}
