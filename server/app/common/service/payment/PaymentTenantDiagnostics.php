<?php
declare(strict_types=1);

namespace app\common\service\payment;

use PeanutAdmin\OpsConsole\Logs\TenantDiagnosticAttributes;
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
