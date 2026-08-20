<?php
declare(strict_types=1);

namespace app\common\service\audit;

use PeanutAdmin\Kernel\Auth\TenantContext;
use PeanutAdmin\Kernel\Context\TenantContextRequirement;

final class OperationLogTenantContext
{
    public static function member(object $request): TenantContext
    {
        return TenantContextRequirement::fromRequest($request);
    }

    public static function tenantId(TenantContext $context): int
    {
        return TenantContextRequirement::tenantId($context);
    }
}
