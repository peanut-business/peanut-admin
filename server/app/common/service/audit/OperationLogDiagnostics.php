<?php
declare(strict_types=1);

namespace app\common\service\audit;

use PeanutAdmin\Kernel\Auth\TenantContext;
use PeanutAdmin\OpsConsole\Logs\TenantDiagnosticAttributes;

final class OperationLogDiagnostics
{
    /** @return array{scope:string,tenant_id:int|null,request_id:string} */
    public static function attributes(?TenantContext $context): array
    {
        return TenantDiagnosticAttributes::fromTenantContext($context);
    }
}
