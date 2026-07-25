<?php
declare(strict_types=1);

namespace app\adminapi\logic\auth;

use app\common\logic\BaseLogic;
use app\common\model\auth\Admin;
use app\common\model\auth\AdminRole;
use think\facade\Db;

class AdminLogic extends BaseLogic
{
    public static function lists(): array
    {
        return Admin::with(['roles'])->order('id', 'desc')->select()->toArray();
    }

    public static function detail(int $id): array
    {
        $admin = Admin::with(['roles'])->findOrEmpty($id);
        if ($admin->isEmpty()) return [];
        $data = $admin->toArray();
        $data['role_ids'] = array_column($data['roles'] ?? [], 'id');
        return $data;
    }

    public static function add(array $params): bool
    {
        Db::startTrans();
        try {
            $salt  = substr(md5((string)time()), 0, 8);
            $admin = Admin::create([
                'username' => $params['username'], 'nickname' => $params['nickname'] ?? $params['username'],
                'password' => $params['password'], 'salt' => $salt,
                'avatar' => $params['avatar'] ?? '', 'root' => $params['root'] ?? 0, 'disable' => $params['disable'] ?? 0,
            ]);
            if (!empty($params['role_ids'])) {
                $rows = array_map(fn($rid) => ['admin_id' => $admin->id, 'role_id' => $rid], $params['role_ids']);
                (new AdminRole)->insertAll($rows);
            }
            Db::commit(); return true;
        } catch (\Throwable $e) { Db::rollback(); self::setError($e->getMessage()); return false; }
    }

    public static function edit(array $params): bool
    {
        Db::startTrans();
        try {
            $data = ['id' => $params['id'], 'nickname' => $params['nickname'] ?? '', 'avatar' => $params['avatar'] ?? '', 'root' => $params['root'] ?? 0, 'disable' => $params['disable'] ?? 0];
            if (!empty($params['password'])) {
                $salt = substr(md5((string)time()), 0, 8);
                $data['salt'] = $salt; $data['password'] = $params['password'];
            }
            Admin::update($data);
            AdminRole::where('admin_id', $params['id'])->delete();
            if (!empty($params['role_ids'])) {
                $rows = array_map(fn($rid) => ['admin_id' => $params['id'], 'role_id' => $rid], $params['role_ids']);
                (new AdminRole)->insertAll($rows);
            }
            Db::commit(); return true;
        } catch (\Throwable $e) { Db::rollback(); self::setError($e->getMessage()); return false; }
    }

    public static function delete(int $id): void
    {
        Admin::destroy($id); AdminRole::where('admin_id', $id)->delete();
    }

    public static function updateStatus(int $id, int $disable): void
    {
        Admin::update(['id' => $id, 'disable' => $disable]);
    }
}
