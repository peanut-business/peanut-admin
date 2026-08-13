<?php
declare(strict_types=1);

namespace app\adminapi\controller\setting;

use app\adminapi\controller\BaseAdminController;
use app\adminapi\logic\setting\CustomerServiceLogic;
use app\common\service\customer_service\CustomerServiceTenantContext;

class CustomerServiceController extends BaseAdminController
{
    public function getConfig()
    {
        return $this->data(CustomerServiceLogic::getConfig(
            CustomerServiceTenantContext::member($this->request)
        ));
    }

    public function setConfig()
    {
        CustomerServiceLogic::setConfig(
            CustomerServiceTenantContext::member($this->request),
            $this->request->post()
        );
        return $this->success('操作成功');
    }
}
