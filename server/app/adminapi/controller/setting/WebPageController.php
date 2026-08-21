<?php
declare(strict_types=1);

namespace app\adminapi\controller\setting;

use app\adminapi\controller\BaseAdminController;
use app\adminapi\logic\setting\WebPageLogic;
use app\adminapi\validate\setting\WebPageValidate;
use app\common\service\member\MemberTenantContext;

class WebPageController extends BaseAdminController
{
    public function getConfig()
    {
        return $this->data(WebPageLogic::getConfig(MemberTenantContext::member($this->request)));
    }

    public function setConfig()
    {
        $params = $this->request->post();
        $this->validate($params, WebPageValidate::class);
        WebPageLogic::setConfig(MemberTenantContext::member($this->request), $params);
        return $this->success('操作成功');
    }
}
