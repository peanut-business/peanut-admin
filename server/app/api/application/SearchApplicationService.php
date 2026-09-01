<?php
declare(strict_types=1);

namespace app\api\application;

use app\common\service\config\TenantApplicationSettingService;
use app\common\service\hot_search\HotSearchTenantRepository;
use PeanutAdmin\Kernel\Auth\TenantContext;
use PeanutAdmin\Kernel\Context\TenantSystemContext;

class SearchApplicationService
{
    public function __construct(private readonly TenantApplicationSettingService $applicationSettings)
    {
    }

    /** 热门搜索列表 */
    public function hotLists(TenantContext|TenantSystemContext $context): array
    {
        $data = HotSearchTenantRepository::terms()
            ->field(['name', 'sort'])
            ->order(['sort' => 'desc', 'id' => 'desc'])
            ->select()
            ->toArray();

        return [
            'status' => (int)$this->applicationSettings->hotSearch($context)['status'],
            'data'   => $data,
        ];
    }
}
