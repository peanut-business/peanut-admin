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
            // 只更新显式传入的字段，避免用默认值覆盖未提交字段（如 root/avatar）——
            // 否则编辑超管时前端未传 root 会被静默降级为 0，造成越权/自锁。
            $data = ['id' => $params['id']];
            foreach (['nickname', 'avatar'] as $f) {
                if (isset($params[$f])) $data[$f] = (string)$params[$f];
            }
            foreach (['root', 'disable'] as $f) {
                if (isset($params[$f])) $data[$f] = (int)$params[$f];
            }
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

    public static function delete(int $id, int $selfId = 0): bool
    {
        if (!self::guard($id, $selfId)) return false;
        Admin::destroy($id); AdminRole::where('admin_id', $id)->delete();
        return true;
    }

    public static function updateStatus(int $id, int $disable, int $selfId = 0): bool
    {
        if (!self::guard($id, $selfId)) return false;
        Admin::update(['id' => $id, 'disable' => $disable]);
        return true;
    }

    /** 防自锁：不能删除/禁用自己，也不能操作超级管理员(root) */
    protected static function guard(int $id, int $selfId): bool
    {
        if ($selfId > 0 && $id === $selfId) { self::setError('不能操作当前登录的管理员'); return false; }
        $target = Admin::findOrEmpty($id);
        if (!$target->isEmpty() && (int)$target->root === 1) { self::setError('超级管理员不可删除或禁用'); return false; }
        return true;
    }

    /**
     * 编辑当前登录管理员的个人信息（昵称/头像/密码）
     * 修改密码需校验当前密码；密码留空则不改。
     */
    public static function editSelf(int $adminId, array $params): bool
    {
        $admin = Admin::findOrEmpty($adminId);
        if ($admin->isEmpty()) { self::setError('管理员不存在'); return false; }

        $data = [
            'id'       => $adminId,
            'nickname' => (string)$params['nickname'],
        ];
        if (isset($params['avatar'])) {
            $data['avatar'] = (string)$params['avatar'];
        }

        if (!empty($params['password'])) {
            $old = (string)($params['password_old'] ?? '');
            if (md5(md5($old) . $admin->salt) !== $admin->password) {
                self::setError('当前密码错误');
                return false;
            }
            // password mutator 用同一数组里的 salt 参与哈希，故一并更新
            $salt = substr(md5((string)time()), 0, 8);
            $data['salt']     = $salt;
            $data['password'] = $params['password'];
        }

        Admin::update($data);
        return true;
    }
}
