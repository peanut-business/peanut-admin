<?php
declare(strict_types=1);

namespace app\adminapi\controller\system;

use think\App;

use app\adminapi\controller\BaseAdminController;
use app\adminapi\application\system\SystemApplicationService;
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
    public function __construct(App $app, private readonly SystemApplicationService $system)
    {
        parent::__construct($app);
    }

    /** 系统环境信息 */
    public function info()
    {
        $denial = $this->instanceToolAccessDenial();
        if ($denial !== null) {
            return $denial;
        }
        return $this->data($this->system->getInfo());
    }

    /** 清除系统缓存 */
    public function clearCache()
    {
        $denial = $this->instanceToolAccessDenial();
        if ($denial !== null) {
            return $denial;
        }

        $this->system->clearCache();
        return $this->success('清除成功');
    }

    private function instanceToolAccessDenial(): ?Json
    {
        $guard = InstanceToolAccessGuard::fromConfiguredValue(config('deployment.mode'));
        return $guard->allows()
            ? null
            : throw \app\common\http\ApiProblem::fromEnvelope('实例级维护工具仅在 standalone 部署中可用', null, 40300);
    }
}
