<?php
declare(strict_types=1);

namespace app\common\service\diagnostics;

use app\common\service\tenant\TenantScope;

/** Structured attribution shared by Tenant-aware background diagnostics. */
final class TenantDiagnosticAttributes
{
    /** @return array{scope:string,tenant_id:int,correlation_id:string} */
    public static function fromScope(TenantScope $scope): array
    {
        return [
            'scope' => 'tenant',
            'tenant_id' => $scope->tenantId(),
            'correlation_id' => $scope->contextIdentity(),
        ];
    }
}
