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
    private const ALGORITHM = 'HS256';
    private const ISSUER = 'peanut-admin';
    private const AUDIENCE = 'peanut-admin-member-api';
    private const SUBJECT_PREFIX = 'member:';

    public static function createToken(int $memberId): string
    {
        if ($memberId < 1) {
            throw new \InvalidArgumentException('会员身份无效');
        }
        $secret = self::secret();
        $expire = self::expire();
        $issuedAt = time();
        if ($expire > PHP_INT_MAX - $issuedAt) {
            throw new \RuntimeException('JWT 有效期配置无效');
        }
        $payload = [
            'iss'       => self::ISSUER,
            'aud'       => self::AUDIENCE,
            'sub'       => self::SUBJECT_PREFIX . $memberId,
            'iat'       => $issuedAt,
            'nbf'       => $issuedAt,
            'exp'       => $issuedAt + $expire,
            'member_id' => $memberId,
        ];
        return JWT::encode($payload, $secret, self::ALGORITHM);
    }

    public static function parseToken(string $token): int|false
    {
        try {
            if ($token === '') {
                return false;
            }
            $decoded = JWT::decode($token, new Key(self::secret(), self::ALGORITHM));
            $memberId = is_int($decoded->member_id ?? null) && $decoded->member_id > 0
                ? $decoded->member_id
                : null;
            $issuedAt = self::timestamp($decoded->iat ?? null);
            $notBefore = self::timestamp($decoded->nbf ?? null);
            $expiresAt = self::timestamp($decoded->exp ?? null);
            if ($memberId === null
                || $issuedAt === null
                || $notBefore === null
                || $expiresAt === null
                || $issuedAt > $notBefore
                || $notBefore >= $expiresAt
                || !is_string($decoded->iss ?? null)
                || !hash_equals(self::ISSUER, $decoded->iss)
                || !is_string($decoded->aud ?? null)
                || !hash_equals(self::AUDIENCE, $decoded->aud)
                || !is_string($decoded->sub ?? null)
                || !hash_equals(self::SUBJECT_PREFIX . $memberId, $decoded->sub)) {
                return false;
            }
            return $memberId;
        } catch (\Throwable) {
            return false;
        }
    }

    private static function secret(): string
    {
        $secret = (string) Config::get('jwt.secret', '');
        if (strlen($secret) < 32) {
            throw new \RuntimeException('JWT_SECRET 必须至少为 32 字节');
        }
        return $secret;
    }

    private static function expire(): int
    {
        $expire = Config::get('jwt.expire');
        if (!is_int($expire) || $expire < 1) {
            throw new \RuntimeException('JWT 有效期配置无效');
        }
        return $expire;
    }

    private static function timestamp(mixed $value): ?int
    {
        if (!is_int($value) || $value < 1) {
            return null;
        }
        return $value;
    }
}
