<?php
declare(strict_types=1);

namespace app\Modules\Official\Oauth\Http\Controller;

use app\adminapi\controller\BaseAdminController;
use app\Modules\Official\Oauth\Service\OpenPlatformLogic;
use app\Modules\Official\Oauth\Validation\OpenPlatformValidate;
use app\common\service\member\MemberTenantContext;

class OpenPlatformController extends BaseAdminController
{
    public function getConfig()
    {
        return $this->data(OpenPlatformLogic::getConfig(MemberTenantContext::member($this->request)));
    }

    public function setConfig()
    {
        $params = $this->request->post();
        $this->validate($params, OpenPlatformValidate::class);
        return OpenPlatformLogic::setConfig(MemberTenantContext::member($this->request), $params)
            ? $this->success('操作成功')
            : $this->fail(OpenPlatformLogic::getError());
    }
}
