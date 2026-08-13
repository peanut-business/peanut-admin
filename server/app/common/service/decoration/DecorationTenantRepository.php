<?php
declare(strict_types=1);

namespace app\common\service\decoration;

use app\common\model\decoration\DecoratePage;
use PeanutAdmin\Kernel\Auth\TenantContext;
use PeanutAdmin\Kernel\Context\TenantSystemContext;

final class DecorationTenantRepository
{
    public static function pages(
        TenantContext|TenantSystemContext $context,
        string $operation = ''
    ) {
        return DecoratePage::where(
            'tenant_id',
            DecorationTenantContext::tenantId($context, $operation)
        );
    }

    private function __construct()
    {
    }
}
