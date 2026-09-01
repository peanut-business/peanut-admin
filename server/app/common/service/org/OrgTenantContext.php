<?php
declare(strict_types=1);

namespace app\common\service\org;

use PeanutAdmin\Kernel\Auth\TenantContext;
use PeanutAdmin\Kernel\Context\TenantContextRequirement;
use app\common\execution\ExecutionContextAccess;

final class OrgTenantContext
{
    public static function member(ExecutionContextAccess $contexts): TenantContext
    {
        $context = $contexts->tenantAdmin();
        TenantContextRequirement::tenantId($context);
        return $context;
    }

    public static function tenantId(mixed $context): int
    {
        return TenantContextRequirement::tenantId($context);
    }

    /** @param array<string,mixed> $params @return array<string,mixed> */
    public static function withoutPayloadTenant(array $params): array
    {
        return TenantContextRequirement::withoutTenantId($params);
    }

    private function __construct()
    {
    }
}
