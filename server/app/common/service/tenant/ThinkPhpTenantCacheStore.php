<?php
declare(strict_types=1);

namespace app\common\service\tenant;

use think\facade\Cache;

/** Framework adapter used only behind TenantCache's scoped logical-key boundary. */
final class ThinkPhpTenantCacheStore implements TenantCacheStore
{
    public function get(string $physicalKey, mixed $default = null): mixed
    {
        return Cache::get($physicalKey, $default);
    }

    public function set(string $physicalKey, mixed $value, int $ttlSeconds = 0): bool
    {
        return (bool) Cache::set($physicalKey, $value, $ttlSeconds);
    }

    public function delete(string $physicalKey): bool
    {
        return (bool) Cache::delete($physicalKey);
    }
}
