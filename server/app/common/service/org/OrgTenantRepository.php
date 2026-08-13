<?php
declare(strict_types=1);

namespace app\common\service\org;

use PeanutAdmin\Kernel\Auth\TenantContext;

final class OrgTenantRepository
{
    public static function query(TenantContext $context, string $modelClass)
    {
        return $modelClass::where('tenant_id', OrgTenantContext::tenantId($context));
    }

    /** @param array<string,mixed> $values */
    public static function create(TenantContext $context, string $modelClass, array $values)
    {
        unset($values['tenant_id']);
        return $modelClass::create(['tenant_id' => OrgTenantContext::tenantId($context)] + $values);
    }

    /** @param int[] $ids */
    public static function assertOwnedIds(
        TenantContext $context,
        string $modelClass,
        array $ids,
        string $message
    ): void {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids), static fn(int $id): bool => $id > 0)));
        if ($ids !== [] && self::query($context, $modelClass)->whereIn('id', $ids)->count() !== count($ids)) {
            throw new \RuntimeException($message);
        }
    }

    private function __construct()
    {
    }
}
