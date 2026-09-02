<?php
declare(strict_types=1);

namespace app\api\service;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

/**
 * 用户端 JWT Token 服务（无状态，与 AdminTokenService 同构）
 */
class UserTokenService
{
    private const ALGORITHM = 'HS256';
    private const ISSUER = 'peanut-admin';
    private const AUDIENCE = 'peanut-admin-member-api';
    private const SUBJECT_PREFIX = 'member:';

    public function __construct(
        private readonly string $secret,
        private readonly int $expire,
    ) {
        if (strlen($this->secret) < 32) {
            throw new \RuntimeException('JWT_SECRET 必须至少为 32 字节');
        }
        if ($this->expire < 1) {
            throw new \RuntimeException('JWT 有效期配置无效');
        }
    }

    public function createToken(int $memberId): string
    {
        if ($memberId < 1) {
            throw new \InvalidArgumentException('会员身份无效');
        }
        $issuedAt = time();
        if ($this->expire > PHP_INT_MAX - $issuedAt) {
            throw new \RuntimeException('JWT 有效期配置无效');
        }
        $payload = [
            'iss'       => self::ISSUER,
            'aud'       => self::AUDIENCE,
            'sub'       => self::SUBJECT_PREFIX . $memberId,
            'iat'       => $issuedAt,
            'nbf'       => $issuedAt,
            'exp'       => $issuedAt + $this->expire,
            'member_id' => $memberId,
        ];
        return JWT::encode($payload, $this->secret, self::ALGORITHM);
    }

    public function parseToken(string $token): int
    {
        try {
            if ($token === '') {
                throw new \UnexpectedValueException('MEMBER_TOKEN_INVALID');
            }
            $decoded = JWT::decode($token, new Key($this->secret, self::ALGORITHM));
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
                throw new \UnexpectedValueException('MEMBER_TOKEN_INVALID');
            }
            return $memberId;
        } catch (\UnexpectedValueException $exception) {
            throw $exception;
        } catch (\Throwable $exception) {
            throw new \UnexpectedValueException('MEMBER_TOKEN_INVALID', 0, $exception);
        }
    }

    private static function timestamp(mixed $value): ?int
    {
        if (!is_int($value) || $value < 1) {
            return null;
        }
        return $value;
    }
}
