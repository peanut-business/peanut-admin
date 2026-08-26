<?php
declare(strict_types=1);

namespace app\common\service\payment;

use app\common\service\finance\FinanceTenantContext;
use PeanutAdmin\Kernel\Tenancy\TenantLockNamespace;
use PeanutAdmin\Kernel\Tenancy\TenantScope;

/** Payment-owned adapter for the tenant-scoped retry lock. */
final class PaymentRetryLock
{
    public static function name(object $context, int $recordId): string
    {
        if ($recordId < 1) {
            throw new \InvalidArgumentException('退款记录 ID 无效');
        }

        $scope = TenantScope::fromTrustedContext(
            FinanceTenantContext::tenantId($context),
            (string)($context->requestId ?? ''),
        );

        return (new TenantLockNamespace($scope))->name('recharge:refund-retry:' . $recordId);
    }
}
