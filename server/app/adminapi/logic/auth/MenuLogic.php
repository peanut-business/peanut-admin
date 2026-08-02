<?php
declare(strict_types=1);

namespace app\adminapi\logic\auth;

use app\adminapi\service\AdminPermissionService;
use app\common\logic\BaseLogic;
use app\common\model\auth\SystemMenu;
use app\common\model\auth\SystemRoleMenu;

class MenuLogic extends BaseLogic
{
    public static function getMenuByAdminId(int $adminId): array
    {
        return AdminPermissionService::menusForAdminId($adminId);
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
        try {
            SystemMenu::create([
                'pid' => $params['pid'] ?? 0, 'type' => $params['type'] ?? 'C',
                'name' => $params['name'], 'icon' => $params['icon'] ?? '',
                'sort' => $params['sort'] ?? 0, 'perms' => $params['perms'] ?? '',
                'paths' => $params['paths'] ?? '', 'component' => $params['component'] ?? '',
                'is_cache' => $params['is_cache'] ?? 0, 'is_show' => $params['is_show'] ?? 1,
                'is_disable' => $params['is_disable'] ?? 0,
            ]);
            return true;
        } catch (\Throwable $e) { self::setError($e->getMessage()); return false; }
    }

    public static function edit(array $params): bool
    {
        try {
            SystemMenu::update([
                'id' => $params['id'], 'pid' => $params['pid'] ?? 0,
                'type' => $params['type'] ?? 'C', 'name' => $params['name'],
                'icon' => $params['icon'] ?? '', 'sort' => $params['sort'] ?? 0,
                'perms' => $params['perms'] ?? '', 'paths' => $params['paths'] ?? '',
                'component' => $params['component'] ?? '',
                'is_cache' => $params['is_cache'] ?? 0, 'is_show' => $params['is_show'] ?? 1,
                'is_disable' => $params['is_disable'] ?? 0,
            ]);
            return true;
        } catch (\Throwable $e) { self::setError($e->getMessage()); return false; }
    }

    public static function delete(int $id): void
    {
        SystemMenu::destroy($id);
        SystemRoleMenu::where('menu_id', $id)->delete();
    }

    public static function updateStatus(int $id, int $isDisable): void
    {
        SystemMenu::update(['id' => $id, 'is_disable' => $isDisable]);
    }
}
