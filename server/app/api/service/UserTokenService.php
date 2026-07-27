<?php
declare(strict_types=1);

namespace app\api\service;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use think\facade\Config;

/**
 * 用户端 JWT Token 服务（无状态，与 AdminTokenService 同构）
 */
class UserTokenService
{
    public static function createToken(int $memberId): string
    {
        $secret  = Config::get('jwt.secret', 'peanut-admin-secret-key');
        $expire  = Config::get('jwt.user_expire', 604800); // 7 天
        $payload = [
            'iss'       => 'peanut-user',
            'iat'       => time(),
            'exp'       => time() + $expire,
            'member_id' => $memberId,
        ];
        return JWT::encode($payload, $secret, 'HS256');
    }

    public static function parseToken(string $token): int|false
    {
        try {
            $secret  = Config::get('jwt.secret', 'peanut-admin-secret-key');
            $decoded = JWT::decode($token, new Key($secret, 'HS256'));
            return (int) $decoded->member_id;
        } catch (\Throwable) {
            return false;
        }
    }
}
