<?php
declare(strict_types=1);

namespace app\Modules\Official\Oauth\Http\Controller;

use app\adminapi\controller\BaseAdminController;
use app\Modules\Official\Oauth\Service\OfficialAccountLogic;
use app\Modules\Official\Oauth\Validation\OfficialAccountValidate;
use app\common\service\member\MemberTenantContext;

class OfficialAccountController extends BaseAdminController
{
    public function getConfig()
    {
        return $this->data(OfficialAccountLogic::getConfig(MemberTenantContext::member($this->request)));
    }

    public function setConfig()
    {
        $params = $this->request->post();
        $this->validate($params, OfficialAccountValidate::class);
        return OfficialAccountLogic::setConfig(MemberTenantContext::member($this->request), $params)
            ? $this->success('操作成功')
            : $this->fail(OfficialAccountLogic::getError());
    }
}
