<?php
declare(strict_types=1);

namespace app\common\service\diagnostics;

use PeanutAdmin\Kernel\Tenancy\TenantScope;
use PeanutAdmin\OpsConsole\Logs\TenantDiagnosticAttributes as CoreTenantDiagnosticAttributes;

/** Structured attribution shared by Tenant-aware background diagnostics. */
final class TenantDiagnosticAttributes
{
    /** @return array{scope:string,tenant_id:int,correlation_id:string} */
    public static function fromScope(TenantScope $scope): array
    {
        return CoreTenantDiagnosticAttributes::fromScope($scope);
    }
}
