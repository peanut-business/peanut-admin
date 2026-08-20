<?php
declare(strict_types=1);

namespace app\common\service\tenant;

use PeanutAdmin\Kernel\Tenancy\TenantCache as CoreTenantCache;
use PeanutAdmin\Kernel\Tenancy\TenantScope;

/**
 * @deprecated Use PeanutAdmin\Kernel\Tenancy\TenantCache directly.
 *
 * This wrapper remains only to preserve the legacy ThinkPHP assembly entry point.
 */
final class TenantCache
{
    private CoreTenantCache $delegate;

    public function __construct(
        TenantScope $scope,
        TenantCacheStore $store
    ) {
        $this->delegate = new CoreTenantCache($scope, $store);
    }

    /** Production assembly: the caller must already hold an upstream-verified scope. */
    public static function thinkPhp(TenantScope $scope): self
    {
        return new self($scope, new ThinkPhpTenantCacheStore());
    }

    public function get(string $logicalKey, mixed $default = null): mixed
    {
        return $this->delegate->get($logicalKey, $default);
    }

    public function set(string $logicalKey, mixed $value, int $ttlSeconds = 0): bool
    {
        return $this->delegate->set($logicalKey, $value, $ttlSeconds);
    }

    public function delete(string $logicalKey): bool
    {
        return $this->delegate->delete($logicalKey);
    }
}
