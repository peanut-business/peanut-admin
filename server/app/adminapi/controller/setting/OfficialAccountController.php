<?php
declare(strict_types=1);

namespace app\adminapi\controller\setting;

use app\adminapi\controller\BaseAdminController;
use app\adminapi\logic\setting\OfficialAccountLogic;
use app\adminapi\validate\setting\OfficialAccountValidate;

class OfficialAccountController extends BaseAdminController
{
    public function getConfig()
    {
        return $this->data(OfficialAccountLogic::getConfig());
    }

    public function setConfig()
    {
        $params = $this->request->post();
        $this->validate($params, OfficialAccountValidate::class);
        return OfficialAccountLogic::setConfig($params)
            ? $this->success('操作成功')
            : $this->fail(OfficialAccountLogic::getError());
    }
}
