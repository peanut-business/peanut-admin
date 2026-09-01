<?php
declare(strict_types=1);

namespace app\common\service\transaction;

use app\common\execution\ExecutionContextAccess;
use PeanutAdmin\Kernel\Auth\TenantContext;
use PeanutAdmin\Kernel\Context\TenantContextRequirement;

/** @deprecated Application compatibility bridge to the core TenantContext requirement. */
final class TransactionSettingTenantContext
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
