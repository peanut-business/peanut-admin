<?php
declare(strict_types=1);

namespace app\adminapi\service;

use app\common\model\auth\AdminSession;
use think\facade\Config;

class AdminTokenService
{
    /**
     * 按管理员与终端维持唯一服务端会话。
     * 允许多处登录时复用有效 token；否则每次登录替换 token，使旧客户端失效。
     */
    public static function createToken(
        int $adminId,
        int $terminal = 1,
        int $multipointLogin = 1,
        string $loginIp = ''
    ): string
    {
        $now     = time();
        $loginIp = $loginIp !== '' ? $loginIp : request()->ip();
        $session = AdminSession::where('admin_id', $adminId)
            ->where('terminal', $terminal)
            ->findOrEmpty();

        if ($session->isEmpty()) {
            $session = AdminSession::create([
                'admin_id'    => $adminId,
                'terminal'    => $terminal,
                'token'       => self::newToken(),
                'login_ip'    => $loginIp,
                'update_time' => $now,
                'expire_time' => $now + self::expireDuration(),
            ]);
            return (string)$session->token;
        }

        if ((int)$session->expire_time <= $now || $multipointLogin === 0) {
            $session->token = self::newToken();
        }
        $session->login_ip    = $loginIp;
        $session->update_time = $now;
        $session->expire_time = $now + self::expireDuration();
        $session->save();

        return (string)$session->token;
    }

    public static function parseToken(string $token): int|false
    {
        $session = self::resolveToken($token);
        if ($session === false) {
            return false;
        }
        return (int)$session['admin_id'];
    }

    /**
     * 服务端校验 token，并在最后一小时内自动续期八小时。
     */
    public static function resolveToken(string $token): array|false
    {
        if ($token === '') {
            return false;
        }

        $now     = time();
        $session = AdminSession::where('token', $token)->findOrEmpty();
        if ($session->isEmpty() || (int)$session->expire_time <= $now) {
            return false;
        }

        if ($now > (int)$session->expire_time - self::renewBeforeExpire()) {
            $session->expire_time = $now + self::expireDuration();
            $session->update_time = $now;
            $session->save();
        }

        return $session->toArray();
    }

    /**
     * 参考 LikeAdmin logout 语义：允许多处登录时服务端不注销。
     */
    public static function expireToken(string $token, bool $force = false): bool
    {
        $session = AdminSession::where('token', $token)->with(['admin'])->findOrEmpty();
        if ($session->isEmpty()) {
            return false;
        }
        if (!$force && (int)($session->admin->multipoint_login ?? 1) === 1) {
            return false;
        }

        $now = time();
        $session->expire_time = $now;
        $session->update_time = $now;
        $session->save();
        return true;
    }

    /**
     * 为迁移前已存在但尚未绑定 IP 的会话执行首次绑定。
     */
    public static function bindLoginIp(string $token, string $loginIp): bool
    {
        $updated = AdminSession::where('token', $token)
            ->where('login_ip', '')
            ->update(['login_ip' => $loginIp]);
        if ($updated > 0) {
            return true;
        }

        return AdminSession::where('token', $token)
            ->where('login_ip', $loginIp)
            ->count() > 0;
    }

    public static function tokenFromRequest($request): string
    {
        $authorization = trim((string)$request->header('Authorization', ''));
        if (preg_match('/^Bearer\s+(.+)$/i', $authorization, $matches)) {
            return trim($matches[1]);
        }

        // 兼容 LikeAdmin 原 token header，但标准入口仍为 Bearer。
        return trim((string)$request->header('token', ''));
    }

    private static function newToken(): string
    {
        return bin2hex(random_bytes(32));
    }

    private static function expireDuration(): int
    {
        return max(1, (int)Config::get('admin_auth.token_expire_duration', 8 * 60 * 60));
    }

    private static function renewBeforeExpire(): int
    {
        return max(0, (int)Config::get('admin_auth.renew_before_expire', 60 * 60));
    }
}
