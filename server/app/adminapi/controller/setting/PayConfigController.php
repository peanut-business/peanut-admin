<?php
declare(strict_types=1);

namespace app\adminapi\controller\setting;

use app\adminapi\controller\BaseAdminController;
use app\adminapi\logic\setting\PayConfigLogic;

class PayConfigController extends BaseAdminController
{
    public function getConfig()
    {
        return $this->data(PayConfigLogic::getConfig());
    }

    public function setConfig()
    {
        PayConfigLogic::setConfig($this->request->post());
        return $this->success('操作成功');
    }
}
