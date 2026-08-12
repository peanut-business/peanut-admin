<?php
declare(strict_types=1);

namespace app\common\service\audit;

use PeanutAdmin\Kernel\Auth\TenantContext;

final class OperationLogDiagnostics
{
    /** @return array{scope:string,tenant_id:int|null,request_id:string} */
    public static function attributes(?TenantContext $context): array
    {
        if ($context === null) {
            return ['scope' => 'unavailable', 'tenant_id' => null, 'request_id' => ''];
        }
        return [
            'scope' => 'tenant',
            'tenant_id' => OperationLogTenantContext::tenantId($context),
            'request_id' => $context->requestId,
        ];
    }
}
