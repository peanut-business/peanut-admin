<?php
declare(strict_types=1);

namespace app\adminapi\logic\auth;

use app\common\logic\BaseLogic;
use app\common\model\auth\SystemRole;
use app\common\model\auth\SystemRoleMenu;
use think\facade\Db;

class RoleLogic extends BaseLogic
{
    public static function getAll(): array
    {
        return SystemRole::order(['sort' => 'desc', 'id' => 'desc'])->select()->toArray();
    }

    public static function detail(int $id): array
    {
        $role    = SystemRole::findOrEmpty($id);
        $menuIds = SystemRoleMenu::where('role_id', $id)->column('menu_id');
        $data    = $role->toArray();
        $data['menu_ids'] = $menuIds;
        return $data;
    }

    public static function add(array $params): bool
    {
        Db::startTrans();
        try {
            $role = SystemRole::create(['name' => $params['name'], 'desc' => $params['desc'] ?? '', 'sort' => $params['sort'] ?? 0]);
            if (!empty($params['menu_ids'])) {
                $rows = array_map(fn($mid) => ['role_id' => $role->id, 'menu_id' => $mid], $params['menu_ids']);
                (new SystemRoleMenu)->insertAll($rows);
            }
            Db::commit(); return true;
        } catch (\Throwable $e) { Db::rollback(); self::setError($e->getMessage()); return false; }
    }

    public static function edit(array $params): bool
    {
        Db::startTrans();
        try {
            SystemRole::update(['id' => $params['id'], 'name' => $params['name'], 'desc' => $params['desc'] ?? '', 'sort' => $params['sort'] ?? 0]);
            SystemRoleMenu::where('role_id', $params['id'])->delete();
            if (!empty($params['menu_ids'])) {
                $rows = array_map(fn($mid) => ['role_id' => $params['id'], 'menu_id' => $mid], $params['menu_ids']);
                (new SystemRoleMenu)->insertAll($rows);
            }
            Db::commit(); return true;
        } catch (\Throwable $e) { Db::rollback(); self::setError($e->getMessage()); return false; }
    }

    public static function delete(int $id): void
    {
        SystemRole::destroy($id);
        SystemRoleMenu::where('role_id', $id)->delete();
    }
}
