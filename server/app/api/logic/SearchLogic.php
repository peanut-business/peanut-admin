<?php
declare(strict_types=1);

namespace app\api\logic;

use app\common\logic\BaseLogic;
use app\common\service\ConfigService;
use app\common\service\hot_search\HotSearchTenantRepository;
use PeanutAdmin\Kernel\Auth\TenantContext;
use PeanutAdmin\Kernel\Context\TenantSystemContext;

class SearchLogic extends BaseLogic
{
    /** 热门搜索列表 */
    public static function hotLists(TenantContext|TenantSystemContext|null $context = null): array
    {
        $data = HotSearchTenantRepository::terms($context)
            ->field(['name', 'sort'])
            ->order(['sort' => 'desc', 'id' => 'desc'])
            ->select()
            ->toArray();

        return [
            'status' => (int) ConfigService::get('hot_search', 'status', 0),
            'data'   => $data,
        ];
    }
}
