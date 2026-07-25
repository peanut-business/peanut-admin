<?php
declare(strict_types=1);

namespace app\adminapi\service;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use think\facade\Config;

class AdminTokenService
{
    public static function createToken(int $adminId): string
    {
        $secret  = Config::get('jwt.secret', 'peanut-admin-secret-key');
        $expire  = Config::get('jwt.expire', 7200);
        $payload = [
            'iss'      => 'peanut-admin',
            'iat'      => time(),
            'exp'      => time() + $expire,
            'admin_id' => $adminId,
        ];
        return JWT::encode($payload, $secret, 'HS256');
    }

    public static function parseToken(string $token): int|false
    {
        try {
            $secret  = Config::get('jwt.secret', 'peanut-admin-secret-key');
            $decoded = JWT::decode($token, new Key($secret, 'HS256'));
            return (int) $decoded->admin_id;
        } catch (\Throwable) {
            return false;
        }
    }
}
