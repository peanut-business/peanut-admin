<?php
declare(strict_types=1);

namespace app\adminapi\logic\auth;

use app\common\logic\BaseLogic;
use app\common\model\auth\AdminRole;
use app\common\model\auth\SystemMenu;
use app\common\model\auth\SystemRole;
use app\common\model\auth\SystemRoleMenu;
use app\common\service\org\OrgTenantContext;
use app\common\service\org\OrgTenantRepository;
use PeanutAdmin\Kernel\Auth\TenantContext;
use think\facade\Db;

class RoleLogic extends BaseLogic
{
    /**
     * 分页角色列表。
     *
     * @return array{lists: array, count: int, pageNo: int, pageSize: int}
     */
    public static function validationRules(string $scene): array
    {
        return $scene === 'add'
            ? ['name' => 'require|length:1,50', 'menu_id' => 'array']
            : ['id' => 'require|integer|gt:0', 'name' => 'require|length:1,50', 'menu_id' => 'array'];
    }

    public static function lists(TenantContext $context, array $params): array
    {
        $pageNo   = max(1, (int)($params['page_no'] ?? 1));
        $pageSize = min(100, max(1, (int)($params['page_size'] ?? 15)));

        $count = self::roles($context)->count();
        $lists = self::roles($context)->field('id,name,desc,sort,create_time')
            ->order(['sort' => 'desc', 'id' => 'desc'])
            ->page($pageNo, $pageSize)
            ->select()
            ->toArray();

        self::appendRoleRelations($context, $lists);
        return compact('lists', 'count', 'pageNo', 'pageSize');
    }

    public static function getAll(TenantContext $context): array
    {
        return self::roles($context)->field('id,name,desc,sort')
            ->order(['sort' => 'desc', 'id' => 'desc'])
            ->select()
            ->toArray();
    }

    public static function detail(TenantContext $context, int $id): array
    {
        $role = self::roles($context)->where('id', $id)->findOrEmpty();
        if ($role->isEmpty()) {
            return [];
        }
        $menuIds = self::roleMenus($context)->where('role_id', $id)->column('menu_id');
        $data    = $role->toArray();
        $data['menu_id']  = array_map('intval', $menuIds);
        // 兼容现有 Peanut 前端读取；新调用方应使用 menu_id。
        $data['menu_ids'] = $data['menu_id'];
        return $data;
    }

    public static function add(TenantContext $context, array $params): bool
    {
        Db::startTrans();
        try {
            self::assertUniqueName($context, (string)$params['name']);
            $role = OrgTenantRepository::create($context, SystemRole::class, [
                'name' => $params['name'],
                'desc' => $params['desc'] ?? '',
                'sort' => $params['sort'] ?? 0,
            ]);
            $menuIds = self::menuIds($params);
            self::assertMenus($menuIds);
            if ($menuIds !== []) {
                $rows = array_map(
                    fn(int $menuId): array => ['tenant_id' => OrgTenantContext::tenantId($context), 'role_id' => $role->id, 'menu_id' => $menuId],
                    $menuIds
                );
                (new SystemRoleMenu)->insertAll($rows);
            }
            Db::commit();
            return true;
        } catch (\Throwable $e) {
            Db::rollback();
            self::setError($e->getMessage());
            return false;
        }
    }

    public static function edit(TenantContext $context, array $params): bool
    {
        Db::startTrans();
        try {
            $roleId = (int)$params['id'];
            $role = self::roles($context)->where('id', $roleId)->lock(true)->findOrEmpty();
            if ($role->isEmpty()) {
                throw new \RuntimeException('角色不存在');
            }
            self::assertUniqueName($context, (string)$params['name'], $roleId);
            $role->save([
                'name' => $params['name'],
                'desc' => $params['desc'] ?? '',
                'sort' => $params['sort'] ?? 0,
            ]);

            $menuIds = self::menuIds($params);
            self::assertMenus($menuIds);
            // 对齐 likeadmin 1.9.4：空 menu_id 表示本次不改权限，而不是清空权限。
            if ($menuIds !== []) {
                self::roleMenus($context)->where('role_id', $roleId)->delete();
                $rows = array_map(
                    fn(int $menuId): array => ['tenant_id' => OrgTenantContext::tenantId($context), 'role_id' => $roleId, 'menu_id' => $menuId],
                    $menuIds
                );
                (new SystemRoleMenu)->insertAll($rows);
            }
            Db::commit();
            return true;
        } catch (\Throwable $e) {
            Db::rollback();
            self::setError($e->getMessage());
            return false;
        }
    }

    public static function delete(TenantContext $context, int $id): bool
    {
        Db::startTrans();
        try {
            $role = self::roles($context)->where('id', $id)->lock(true)->findOrEmpty();
            if ($role->isEmpty()) {
                throw new \RuntimeException('角色不存在');
            }
            if (OrgTenantRepository::query($context, AdminRole::class)->where('role_id', $id)->count() > 0) {
                throw new \RuntimeException('有管理员在使用该角色，不允许删除');
            }

            $role->delete();
            self::roleMenus($context)->where('role_id', $id)->delete();
            Db::commit();
            return true;
        } catch (\Throwable $e) {
            Db::rollback();
            self::setError($e->getMessage());
            return false;
        }
    }

    /** 为列表批量补充管理员人数和授权菜单，避免逐角色 N+1 查询。 */
    private static function appendRoleRelations(TenantContext $context, array &$lists): void
    {
        $roleIds = array_map('intval', array_column($lists, 'id'));
        if ($roleIds === []) {
            return;
        }

        $adminRows = OrgTenantRepository::query($context, AdminRole::class)->whereIn('role_id', $roleIds)
            ->field('role_id,COUNT(*) AS num')
            ->group('role_id')
            ->select()
            ->toArray();
        $adminCounts = [];
        foreach ($adminRows as $row) {
            $adminCounts[(int)$row['role_id']] = (int)$row['num'];
        }

        $menuRows = self::roleMenus($context)->whereIn('role_id', $roleIds)
            ->field('role_id,menu_id')
            ->order('menu_id', 'asc')
            ->select()
            ->toArray();
        $roleMenus = [];
        foreach ($menuRows as $row) {
            $roleMenus[(int)$row['role_id']][] = (int)$row['menu_id'];
        }

        foreach ($lists as &$role) {
            $roleId = (int)$role['id'];
            $role['num'] = $adminCounts[$roleId] ?? 0;
            $role['menu_id'] = $roleMenus[$roleId] ?? [];
        }
        unset($role);
    }

    /** @return int[] */
    private static function menuIds(array $params): array
    {
        $menuIds = $params['menu_id'] ?? [];
        if (!is_array($menuIds)) {
            return [];
        }
        $menuIds = array_map('intval', $menuIds);
        $menuIds = array_filter($menuIds, fn(int $menuId): bool => $menuId > 0);
        return array_values(array_unique($menuIds));
    }

    private static function roles(TenantContext $context)
    {
        return OrgTenantRepository::query($context, SystemRole::class);
    }

    private static function roleMenus(TenantContext $context)
    {
        return OrgTenantRepository::query($context, SystemRoleMenu::class);
    }

    private static function assertUniqueName(TenantContext $context, string $name, int $exceptId = 0): void
    {
        $query = self::roles($context)->where('name', trim($name));
        if ($exceptId > 0) {
            $query->where('id', '<>', $exceptId);
        }
        if ($query->count() > 0) {
            throw new \RuntimeException('角色名称已存在');
        }
    }

    private static function assertMenus(array $menuIds): void
    {
        if ($menuIds !== [] && SystemMenu::whereIn('id', $menuIds)->count() !== count($menuIds)) {
            throw new \RuntimeException('菜单权限不存在');
        }
    }
}
