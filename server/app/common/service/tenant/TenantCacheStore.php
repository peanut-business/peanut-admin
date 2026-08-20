<?php
declare(strict_types=1);

namespace app\common\service\tenant;

use PeanutAdmin\Kernel\Tenancy\TenantCacheStore as CoreTenantCacheStore;

/**
 * @deprecated Use PeanutAdmin\Kernel\Tenancy\TenantCacheStore directly.
 */
interface TenantCacheStore extends CoreTenantCacheStore
{
}
