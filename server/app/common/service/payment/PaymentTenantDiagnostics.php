<?php
declare(strict_types=1);

namespace app\common\service\payment;

use app\common\service\diagnostics\TenantDiagnosticAttributes;
use PeanutAdmin\Kernel\Tenancy\TenantScope;

/** Payment-owned adapter for reconciliation diagnostics. */
final class PaymentTenantDiagnostics
{
    /** @return array<string,mixed> */
    public static function fromScope(TenantScope $scope): array
    {
        return TenantDiagnosticAttributes::fromScope($scope);
    }
}
