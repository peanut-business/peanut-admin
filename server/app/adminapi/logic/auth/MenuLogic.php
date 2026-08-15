<?php
declare(strict_types=1);

namespace app\adminapi\logic\auth;

use app\adminapi\service\AdminPermissionService;
use app\common\logic\BaseLogic;
use app\common\model\auth\SystemMenu;
use think\facade\Db;

class MenuLogic extends BaseLogic
{
    public static function getMenuByAdminId(mixed $tenantContext, int $adminId): array
    {
        return AdminPermissionService::menusForAdminId($tenantContext, $adminId);
    }

    public static function getAll(): array
    {
        $menus = SystemMenu::order(['sort' => 'desc', 'id' => 'asc'])->select()->toArray();
        return linear_to_tree($menus);
    }

    public static function getAllSimple(): array
    {
        $data = SystemMenu::where('is_disable', 0)->field('id,pid,name')
            ->order(['sort' => 'desc', 'id' => 'asc'])->select()->toArray();
        return linear_to_tree($data);
    }

    public static function detail(int $id): array
    {
        return SystemMenu::findOrEmpty($id)->toArray();
    }

    public static function add(array $params): bool
    {
        Db::startTrans();
        try {
            self::assertParent((int)($params['pid'] ?? 0));
            SystemMenu::create([
                'pid' => $params['pid'] ?? 0, 'type' => $params['type'] ?? 'C',
                'name' => $params['name'], 'icon' => $params['icon'] ?? '',
                'sort' => $params['sort'] ?? 0, 'perms' => $params['perms'] ?? '',
                'paths' => $params['paths'] ?? '', 'component' => $params['component'] ?? '',
                'is_cache' => $params['is_cache'] ?? 0, 'is_show' => $params['is_show'] ?? 1,
                'is_disable' => $params['is_disable'] ?? 0,
            ]);
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
            $id = (int)$params['id'];
            $menu = SystemMenu::where('id', $id)->lock(true)->findOrEmpty();
            if ($menu->isEmpty()) {
                throw new \RuntimeException('菜单不存在');
            }
            self::assertParent((int)($params['pid'] ?? 0), $id);
            $menu->save([
                'pid' => $params['pid'] ?? 0,
                'type' => $params['type'] ?? 'C', 'name' => $params['name'],
                'icon' => $params['icon'] ?? '', 'sort' => $params['sort'] ?? 0,
                'perms' => $params['perms'] ?? '', 'paths' => $params['paths'] ?? '',
                'component' => $params['component'] ?? '',
                'is_cache' => $params['is_cache'] ?? 0, 'is_show' => $params['is_show'] ?? 1,
                'is_disable' => $params['is_disable'] ?? 0,
            ]);
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
            $menu = SystemMenu::where('id', $id)->lock(true)->findOrEmpty();
            if ($menu->isEmpty()) {
                throw new \RuntimeException('菜单不存在');
            }
            if (SystemMenu::where('pid', $id)->count() > 0) {
                throw new \RuntimeException('已关联下级菜单，暂不可删除');
            }
            $permission = trim((string)$menu->perms);
            if ($permission !== '' && self::permissionAssigned($permission)) {
                throw new \RuntimeException('菜单已被角色使用，暂不可删除');
            }

            $menu->delete();
            Db::commit();
            return true;
        } catch (\Throwable $e) {
            Db::rollback();
            self::setError($e->getMessage());
            return false;
        }
    }

    public static function updateStatus(int $id, int $isDisable): bool
    {
        Db::startTrans();
        try {
            $menu = SystemMenu::where('id', $id)->lock(true)->findOrEmpty();
            if ($menu->isEmpty()) {
                throw new \RuntimeException('菜单不存在');
            }
            $menu->save(['is_disable' => $isDisable]);
            Db::commit();
            return true;
        } catch (\Throwable $e) {
            Db::rollback();
            self::setError($e->getMessage());
            return false;
        }
    }

    private static function assertParent(int $parentId, int $menuId = 0): void
    {
        if ($parentId === 0) {
            return;
        }
        if ($parentId === $menuId) {
            throw new \RuntimeException('上级菜单不可是当前菜单');
        }

        $visited = [];
        while ($parentId > 0) {
            if ($parentId === $menuId) {
                throw new \RuntimeException('上级菜单不可是当前菜单或其下级菜单');
            }
            if (isset($visited[$parentId])) {
                throw new \RuntimeException('菜单层级关系异常');
            }
            $visited[$parentId] = true;

            $parent = SystemMenu::where('id', $parentId)->lock(true)->findOrEmpty();
            if ($parent->isEmpty()) {
                throw new \RuntimeException('上级菜单不存在');
            }
            if ((string)$parent->type === 'A') {
                throw new \RuntimeException('按钮不可作为上级菜单');
            }
            $parentId = (int)$parent->pid;
        }
    }

    private static function permissionAssigned(string $permission): bool
    {
        return Db::name('role_permission')->alias('rp')
            ->join('permission p', 'p.id = rp.permission_id')
            ->where('p.key', $permission)
            ->count() > 0;
    }
}
