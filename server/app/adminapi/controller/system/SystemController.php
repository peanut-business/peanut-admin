<?php
declare(strict_types=1);

namespace app\adminapi\controller\system;

use app\adminapi\controller\BaseAdminController;
use app\adminapi\logic\system\SystemLogic;
use app\common\service\instance\InstanceToolAccessGuard;
use app\common\service\JsonService;
use think\response\Json;

/**
 * 系统维护控制器
 * Class SystemController
 * @package app\adminapi\controller\system
 */
class SystemController extends BaseAdminController
{
    /** 系统环境信息 */
    public function info()
    {
        $denial = $this->instanceToolAccessDenial();
        if ($denial !== null) {
            return $denial;
        }
        return $this->data(SystemLogic::getInfo());
    }

    /** 清除系统缓存 */
    public function clearCache()
    {
        $denial = $this->instanceToolAccessDenial();
        if ($denial !== null) {
            return $denial;
        }

        SystemLogic::clearCache();
        return $this->success('清除成功');
    }

    private function instanceToolAccessDenial(): ?Json
    {
        $guard = InstanceToolAccessGuard::fromConfiguredValue(config('deployment.mode'));
        return $guard->allows()
            ? null
            : JsonService::fail('实例级维护工具仅在 standalone 部署中可用', null, 40300);
    }
}
