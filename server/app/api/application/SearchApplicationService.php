<?php
declare(strict_types=1);

namespace app\api\application;

use app\common\application\ApplicationService;
use app\common\service\config\TenantApplicationSettingService;
use app\common\service\hot_search\HotSearchTenantRepository;
use PeanutAdmin\Kernel\Auth\TenantContext;
use PeanutAdmin\Kernel\Context\TenantSystemContext;

class SearchApplicationService extends ApplicationService
{
    /** 热门搜索列表 */
    public function hotLists(TenantContext|TenantSystemContext $context): array
    {
        $data = HotSearchTenantRepository::terms()
            ->field(['name', 'sort'])
            ->order(['sort' => 'desc', 'id' => 'desc'])
            ->select()
            ->toArray();

        return [
            'status' => (int)TenantApplicationSettingService::hotSearch($context)['status'],
            'data'   => $data,
        ];
    }
}
