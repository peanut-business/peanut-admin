<?php
declare(strict_types=1);

namespace app\common\service\runtime;

use think\facade\Cache;

/** Instance-scoped cache adapter whose keys can be cleared without touching peers. */
final class ApplicationCache
{
    public const TAG = 'application:v1';
    private const PREFIX = 'application:v1:';

    public static function get(string $logicalKey, mixed $default = null): mixed
    {
        return Cache::get(self::key($logicalKey), $default);
    }

    public static function set(string $logicalKey, mixed $value, int $ttlSeconds = 0): bool
    {
        if ($ttlSeconds < 0) {
            throw new \InvalidArgumentException('APPLICATION_CACHE_TTL_INVALID');
        }
        return Cache::tag(self::TAG)->set(self::key($logicalKey), $value, $ttlSeconds);
    }

    public static function delete(string $logicalKey): bool
    {
        return (bool) Cache::delete(self::key($logicalKey));
    }

    public static function clear(): bool
    {
        return Cache::tag(self::TAG)->clear();
    }

    private static function key(string $logicalKey): string
    {
        $logicalKey = trim($logicalKey);
        if ($logicalKey === ''
            || strlen($logicalKey) > 512
            || preg_match('/[\x00-\x1F\x7F]/', $logicalKey) === 1) {
            throw new \InvalidArgumentException('APPLICATION_CACHE_KEY_INVALID');
        }
        return self::PREFIX . hash('sha256', $logicalKey);
    }

    private function __construct()
    {
    }
}
