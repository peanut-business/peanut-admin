<?php
declare(strict_types=1);

namespace app\adminapi\controller\setting;

use app\adminapi\controller\BaseAdminController;
use app\adminapi\logic\setting\MiniProgramLogic;
use app\adminapi\validate\setting\MiniProgramValidate;
use app\common\service\member\MemberTenantContext;

class MiniProgramController extends BaseAdminController
{
    public function getConfig()
    {
        return $this->data(MiniProgramLogic::getConfig(MemberTenantContext::member($this->request)));
    }

    public function setConfig()
    {
        $params = $this->request->post();
        $this->validate($params, MiniProgramValidate::class);
        return MiniProgramLogic::setConfig(MemberTenantContext::member($this->request), $params)
            ? $this->success('操作成功')
            : $this->fail(MiniProgramLogic::getError());
    }
}
