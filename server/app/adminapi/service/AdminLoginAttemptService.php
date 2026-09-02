<?php
declare(strict_types=1);

namespace app\adminapi\service;

use app\common\service\runtime\ApplicationCache;

class AdminLoginAttemptService
{
    public function __construct(
        private readonly int $configuredMaxAttempts,
        private readonly int $configuredLockMinutes,
    ) {}

    public function isLocked(string $ip): bool
    {
        return (int)ApplicationCache::get(self::cacheKey($ip), 0) >= $this->maxAttempts();
    }

    public function recordFailure(string $ip): int
    {
        $key   = self::cacheKey($ip);
        $count = (int)ApplicationCache::get($key, 0) + 1;
        ApplicationCache::set($key, $count, $this->lockSeconds());
        return $count;
    }

    public function clear(string $ip): void
    {
        ApplicationCache::delete(self::cacheKey($ip));
    }

    public function lockedMessage(): string
    {
        return sprintf(
            '密码连续%d次输入错误，请%d分钟后重试',
            $this->maxAttempts(),
            $this->lockMinutes()
        );
    }

    private static function cacheKey(string $ip): string
    {
        return 'admin_login_fail_' . sha1($ip);
    }

    private function maxAttempts(): int
    {
        return max(1, $this->configuredMaxAttempts);
    }

    private function lockMinutes(): int
    {
        return max(1, $this->configuredLockMinutes);
    }

    private function lockSeconds(): int
    {
        return $this->lockMinutes() * 60;
    }
}
