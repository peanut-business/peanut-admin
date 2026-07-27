<?php
declare(strict_types=1);

namespace app\adminapi\controller\setting;

use app\adminapi\controller\BaseAdminController;
use app\adminapi\logic\setting\DecorateLogic;

class DecorateController extends BaseAdminController
{
    public function getConfig()
    {
        return $this->data(DecorateLogic::getConfig());
    }

    public function setConfig()
    {
        DecorateLogic::setConfig($this->request->post());
        return $this->success('操作成功');
    }
}
