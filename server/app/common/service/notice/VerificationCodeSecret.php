<?php
declare(strict_types=1);

namespace app\common\service\notice;

/** 验证码只保存单向慢哈希，绝不把可重放明文写入数据库或日志。 */
final class VerificationCodeSecret
{
    public static function hash(string $code): string
    {
        if (preg_match('/^\d{4}$/', $code) !== 1) {
            throw new \InvalidArgumentException('验证码格式无效');
        }
        $hash = password_hash($code, PASSWORD_DEFAULT);
        if (!is_string($hash)) {
            throw new \RuntimeException('验证码哈希失败');
        }
        return $hash;
    }

    public static function matches(string $code, string $hash): bool
    {
        return preg_match('/^\d{4}$/', $code) === 1
            && $hash !== ''
            && password_verify($code, $hash);
    }
}
