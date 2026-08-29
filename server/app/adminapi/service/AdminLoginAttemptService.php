<?php
declare(strict_types=1);

namespace app\adminapi\service;

use app\common\service\runtime\ApplicationCache;
use think\facade\Config;

class AdminLoginAttemptService
{
    public static function isLocked(string $ip): bool
    {
        return (int)ApplicationCache::get(self::cacheKey($ip), 0) >= self::maxAttempts();
    }

    public static function recordFailure(string $ip): int
    {
        $key   = self::cacheKey($ip);
        $count = (int)ApplicationCache::get($key, 0) + 1;
        ApplicationCache::set($key, $count, self::lockSeconds());
        return $count;
    }

    public static function clear(string $ip): void
    {
        ApplicationCache::delete(self::cacheKey($ip));
    }

    public static function lockedMessage(): string
    {
        return sprintf(
            '密码连续%d次输入错误，请%d分钟后重试',
            self::maxAttempts(),
            self::lockMinutes()
        );
    }

    private static function cacheKey(string $ip): string
    {
        return 'admin_login_fail_' . sha1($ip);
    }

    private static function maxAttempts(): int
    {
        return max(1, (int)Config::get('admin_auth.password_error_times', 5));
    }

    private static function lockMinutes(): int
    {
        return max(1, (int)Config::get('admin_auth.lock_minutes', 30));
    }

    private static function lockSeconds(): int
    {
        return self::lockMinutes() * 60;
    }
}
