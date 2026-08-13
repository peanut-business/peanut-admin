<?php
declare(strict_types=1);

namespace app\adminapi\controller\setting;

use app\adminapi\controller\BaseAdminController;
use app\adminapi\logic\setting\PayConfigLogic;
use app\adminapi\validate\setting\PayConfigValidate;
use app\common\service\member\MemberTenantContext;

class PayConfigController extends BaseAdminController
{
    public function getConfig()
    {
        return $this->data(PayConfigLogic::getConfig(MemberTenantContext::member($this->request)));
    }

    public function setConfig()
    {
        $params = $this->request->post();
        $this->validate($params, PayConfigValidate::class);
        $result = PayConfigLogic::setConfig(MemberTenantContext::member($this->request), $params);
        return $result ? $this->success('操作成功') : $this->fail(PayConfigLogic::getError());
    }
}
