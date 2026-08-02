<?php
declare(strict_types=1);

namespace app\adminapi\controller\setting;

use app\adminapi\controller\BaseAdminController;
use app\adminapi\logic\setting\WebPageLogic;
use app\adminapi\validate\setting\WebPageValidate;

class WebPageController extends BaseAdminController
{
    public function getConfig()
    {
        return $this->data(WebPageLogic::getConfig());
    }

    public function setConfig()
    {
        $params = $this->request->post();
        $this->validate($params, WebPageValidate::class);
        WebPageLogic::setConfig($params);
        return $this->success('操作成功');
    }
}
