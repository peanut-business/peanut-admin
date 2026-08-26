<?php
declare(strict_types=1);

namespace app\Modules\Official\Oauth\Http\Controller;

use app\adminapi\controller\BaseAdminController;
use app\Modules\Official\Oauth\Service\OfficialAccountMenuLogic;
use app\Modules\Official\Oauth\Validation\OfficialAccountMenuValidate;
use app\common\service\member\MemberTenantContext;

class OfficialAccountMenuController extends BaseAdminController
{
    public function detail()
    {
        return $this->data(OfficialAccountMenuLogic::detail(MemberTenantContext::member($this->request)));
    }

    public function save()
    {
        $params = $this->request->post();
        $this->validate($params, OfficialAccountMenuValidate::class);
        $result = OfficialAccountMenuLogic::save(
            MemberTenantContext::member($this->request),
            (array)$params['menu']
        );
        return $result ? $this->success('保存成功') : $this->fail(OfficialAccountMenuLogic::getError());
    }

    public function saveAndPublish()
    {
        $params = $this->request->post();
        $this->validate($params, OfficialAccountMenuValidate::class);
        $result = OfficialAccountMenuLogic::saveAndPublish(
            MemberTenantContext::member($this->request),
            (array)$params['menu']
        );
        return $result ? $this->success('发布成功') : $this->fail(OfficialAccountMenuLogic::getError());
    }
}
