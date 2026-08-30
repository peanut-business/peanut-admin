<?php
declare(strict_types=1);

namespace app\common\service\hot_search;

use app\common\model\setting\HotSearch;

final class HotSearchTenantRepository
{
    public static function terms()
    {
        return HotSearch::where([]);
    }

    /** @param list<array{name:string,sort:int}> $rows */
    public static function replace(array $rows): void
    {
        self::terms()->delete();
        if ($rows === []) {
            return;
        }

        (new HotSearch())->saveAll($rows);
    }
}
