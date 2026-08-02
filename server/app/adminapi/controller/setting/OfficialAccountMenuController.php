<?php
declare(strict_types=1);

namespace app\adminapi\controller\setting;

use app\adminapi\controller\BaseAdminController;
use app\adminapi\logic\setting\OfficialAccountMenuLogic;
use app\adminapi\validate\setting\OfficialAccountMenuValidate;

class OfficialAccountMenuController extends BaseAdminController
{
    public function detail()
    {
        return $this->data(OfficialAccountMenuLogic::detail());
    }

    public function save()
    {
        $params = $this->request->post();
        $this->validate($params, OfficialAccountMenuValidate::class);
        $result = OfficialAccountMenuLogic::save((array)$params['menu']);
        return $result ? $this->success('保存成功') : $this->fail(OfficialAccountMenuLogic::getError());
    }

    public function saveAndPublish()
    {
        $params = $this->request->post();
        $this->validate($params, OfficialAccountMenuValidate::class);
        $result = OfficialAccountMenuLogic::saveAndPublish((array)$params['menu']);
        return $result ? $this->success('发布成功') : $this->fail(OfficialAccountMenuLogic::getError());
    }
}
