<?php
declare(strict_types=1);

namespace app\common\service\audit;

use app\common\execution\ExecutionContextAccess;
use PeanutAdmin\Kernel\Auth\TenantContext;
use PeanutAdmin\Kernel\Context\TenantContextRequirement;

final class OperationLogTenantContext
{
    public static function member(ExecutionContextAccess $contexts): TenantContext
    {
        $context = $contexts->tenantAdmin();
        TenantContextRequirement::tenantId($context);
        return $context;
    }

    public static function tenantId(TenantContext $context): int
    {
        return TenantContextRequirement::tenantId($context);
    }
}
