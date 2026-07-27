<?php
declare(strict_types=1);

namespace app\adminapi\controller\system;

use app\adminapi\controller\BaseAdminController;
use app\adminapi\logic\system\SystemLogic;

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
        return $this->data(SystemLogic::getInfo());
    }

    /** 清除系统缓存 */
    public function clearCache()
    {
        SystemLogic::clearCache();
        return $this->success('清除成功');
    }
}
