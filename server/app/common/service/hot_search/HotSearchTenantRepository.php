<?php
declare(strict_types=1);

namespace app\common\service\hot_search;

use app\common\model\setting\HotSearch;
use PeanutAdmin\Kernel\Auth\TenantContext;
use PeanutAdmin\Kernel\Context\TenantSystemContext;

final class HotSearchTenantRepository
{
    public static function terms(TenantContext|TenantSystemContext|null $context)
    {
        return HotSearch::where('tenant_id', HotSearchTenantContext::tenantId($context));
    }

    /** @param list<array{name:string,sort:int}> $rows */
    public static function replace(TenantContext $context, array $rows): void
    {
        $tenantId = HotSearchTenantContext::tenantId($context);
        self::terms($context)->delete();
        if ($rows === []) {
            return;
        }

        $ownedRows = array_map(
            static fn(array $row): array => ['tenant_id' => $tenantId] + $row,
            $rows
        );
        (new HotSearch())->saveAll($ownedRows);
    }
}
