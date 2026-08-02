<?php
declare(strict_types=1);

namespace app\adminapi\logic\auth;

use app\common\logic\BaseLogic;
use app\common\model\auth\AdminRole;
use app\common\model\auth\SystemRole;
use app\common\model\auth\SystemRoleMenu;
use think\facade\Db;

class RoleLogic extends BaseLogic
{
    /**
     * 分页角色列表。
     *
     * @return array{lists: array, count: int, pageNo: int, pageSize: int}
     */
    public static function lists(array $params): array
    {
        $pageNo   = max(1, (int)($params['page_no'] ?? 1));
        $pageSize = min(100, max(1, (int)($params['page_size'] ?? 15)));

        $count = SystemRole::count();
        $lists = SystemRole::field('id,name,desc,sort,create_time')
            ->order(['sort' => 'desc', 'id' => 'desc'])
            ->page($pageNo, $pageSize)
            ->select()
            ->toArray();

        self::appendRoleRelations($lists);
        return compact('lists', 'count', 'pageNo', 'pageSize');
    }

    public static function getAll(): array
    {
        return SystemRole::field('id,name,desc,sort')
            ->order(['sort' => 'desc', 'id' => 'desc'])
            ->select()
            ->toArray();
    }

    public static function detail(int $id): array
    {
        $role    = SystemRole::findOrEmpty($id);
        $menuIds = SystemRoleMenu::where('role_id', $id)->column('menu_id');
        $data    = $role->toArray();
        $data['menu_id']  = array_map('intval', $menuIds);
        // 兼容现有 Peanut 前端读取；新调用方应使用 menu_id。
        $data['menu_ids'] = $data['menu_id'];
        return $data;
    }

    public static function add(array $params): bool
    {
        Db::startTrans();
        try {
            $role = SystemRole::create([
                'name' => $params['name'],
                'desc' => $params['desc'] ?? '',
                'sort' => $params['sort'] ?? 0,
            ]);
            $menuIds = self::menuIds($params);
            if ($menuIds !== []) {
                $rows = array_map(
                    fn(int $menuId): array => ['role_id' => $role->id, 'menu_id' => $menuId],
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

    public static function edit(array $params): bool
    {
        Db::startTrans();
        try {
            $roleId = (int)$params['id'];
            SystemRole::update([
                'id' => $roleId,
                'name' => $params['name'],
                'desc' => $params['desc'] ?? '',
                'sort' => $params['sort'] ?? 0,
            ]);

            $menuIds = self::menuIds($params);
            // 对齐 likeadmin 1.9.4：空 menu_id 表示本次不改权限，而不是清空权限。
            if ($menuIds !== []) {
                SystemRoleMenu::where('role_id', $roleId)->delete();
                $rows = array_map(
                    fn(int $menuId): array => ['role_id' => $roleId, 'menu_id' => $menuId],
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

    public static function delete(int $id): bool
    {
        Db::startTrans();
        try {
            SystemRole::destroy($id);
            SystemRoleMenu::where('role_id', $id)->delete();
            Db::commit();
            return true;
        } catch (\Throwable $e) {
            Db::rollback();
            self::setError($e->getMessage());
            return false;
        }
    }

    /** 为列表批量补充管理员人数和授权菜单，避免逐角色 N+1 查询。 */
    private static function appendRoleRelations(array &$lists): void
    {
        $roleIds = array_map('intval', array_column($lists, 'id'));
        if ($roleIds === []) {
            return;
        }

        $adminRows = AdminRole::whereIn('role_id', $roleIds)
            ->field('role_id,COUNT(*) AS num')
            ->group('role_id')
            ->select()
            ->toArray();
        $adminCounts = [];
        foreach ($adminRows as $row) {
            $adminCounts[(int)$row['role_id']] = (int)$row['num'];
        }

        $menuRows = SystemRoleMenu::whereIn('role_id', $roleIds)
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
}
