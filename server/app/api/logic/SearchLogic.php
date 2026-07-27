<?php
declare(strict_types=1);

namespace app\api\logic;

use app\common\logic\BaseLogic;
use app\common\model\setting\HotSearch;
use app\common\service\ConfigService;

class SearchLogic extends BaseLogic
{
    /** 热门搜索列表 */
    public static function hotLists(): array
    {
        $data = HotSearch::field(['name', 'sort'])
            ->order(['sort' => 'desc', 'id' => 'desc'])
            ->select()
            ->toArray();

        return [
            'status' => (int) ConfigService::get('hot_search', 'status', 0),
            'data'   => $data,
        ];
    }
}
