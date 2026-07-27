<?php
declare(strict_types=1);

namespace app\adminapi\controller\setting;

use app\adminapi\controller\BaseAdminController;
use app\adminapi\logic\setting\CustomerServiceLogic;

class CustomerServiceController extends BaseAdminController
{
    public function getConfig()
    {
        return $this->data(CustomerServiceLogic::getConfig());
    }

    public function setConfig()
    {
        CustomerServiceLogic::setConfig($this->request->post());
        return $this->success('操作成功');
    }
}
