<?php
declare(strict_types=1);

namespace app\adminapi\controller\setting;

use app\adminapi\controller\BaseAdminController;
use app\adminapi\logic\setting\OpenPlatformLogic;
use app\adminapi\validate\setting\OpenPlatformValidate;

class OpenPlatformController extends BaseAdminController
{
    public function getConfig()
    {
        return $this->data(OpenPlatformLogic::getConfig());
    }

    public function setConfig()
    {
        $params = $this->request->post();
        $this->validate($params, OpenPlatformValidate::class);
        return OpenPlatformLogic::setConfig($params)
            ? $this->success('操作成功')
            : $this->fail(OpenPlatformLogic::getError());
    }
}
