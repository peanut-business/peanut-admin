<?php
declare(strict_types=1);

namespace app\adminapi\controller\config;

use app\adminapi\controller\BaseAdminController;
use app\adminapi\logic\config\ConfigLogic;
use app\adminapi\validate\config\WebsiteValidate;

class ConfigController extends BaseAdminController
{
    public function getWebsite()
    {
        return $this->data(ConfigLogic::getWebsite());
    }

    public function saveWebsite()
    {
        $this->validate($this->request->post(), WebsiteValidate::class);
        ConfigLogic::saveWebsite($this->request->post());
        return $this->success('操作成功');
    }
}
