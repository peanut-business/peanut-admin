<?php
declare(strict_types=1);

namespace app\adminapi\logic\auth;

use app\adminapi\service\AdminLoginAttemptService;
use app\adminapi\service\AdminTokenService;
use app\common\logic\BaseLogic;
use app\common\model\auth\Admin;

class LoginLogic extends BaseLogic
{
    public static function login(array $params): array|false
    {
        $ip = request()->ip();
        if (AdminLoginAttemptService::isLocked($ip)) {
            self::setError(AdminLoginAttemptService::lockedMessage());
            return false;
        }

        // Peanut 现有账号真源为 username；对外兼容 LikeAdmin 的 account 命名。
        $admin = Admin::with(['roles'])
            ->where('username', (string)$params['account'])
            ->findOrEmpty();

        if ($admin->isEmpty()) {
            self::setError('账号不存在');
            return false;
        }
        if ((int)$admin->disable === 1) {
            self::setError('账号已禁用');
            return false;
        }

        $password = md5(md5((string)$params['password']) . (string)$admin->salt);
        if (!hash_equals((string)$admin->password, $password)) {
            AdminLoginAttemptService::recordFailure($ip);
            self::setError('密码错误');
            return false;
        }

        AdminLoginAttemptService::clear($ip);

        $admin->login_time = time();
        $admin->login_ip   = $ip;
        $admin->save();

        $token = AdminTokenService::createToken(
            (int)$admin->id,
            (int)$params['terminal'],
            (int)($admin->multipoint_login ?? 1),
            $ip
        );
        $roleNames = $admin->roles->column('name');

        return [
            'token'     => $token,
            'admin_id'  => (int)$admin->id,
            'account'   => (string)$admin->username,
            'username'  => (string)$admin->username,
            'name'      => (string)($admin->nickname ?: $admin->username),
            'avatar'    => (string)$admin->avatar,
            'role_name' => implode('/', $roleNames),
            'terminal'  => (int)$params['terminal'],
        ];
    }

    /**
     * 参考 LikeAdmin：允许多处登录时退出仅由客户端丢弃 token；
     * 不允许多处登录时，服务端同时将当前会话置为过期。
     */
    public static function logout(string $token): void
    {
        AdminTokenService::expireToken($token);
    }
}
