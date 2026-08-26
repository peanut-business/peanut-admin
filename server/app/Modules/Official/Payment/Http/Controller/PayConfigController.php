<?php
declare(strict_types=1);

namespace app\Modules\Official\Payment\Http\Controller;

use app\adminapi\controller\BaseAdminController;
use app\Modules\Official\Payment\Service\PayConfigLogic;
use app\Modules\Official\Payment\Validation\PayConfigValidate;
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
