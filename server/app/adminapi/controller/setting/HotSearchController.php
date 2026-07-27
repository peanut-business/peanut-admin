<?php
declare(strict_types=1);

namespace app\adminapi\controller\setting;

use app\adminapi\controller\BaseAdminController;
use app\adminapi\logic\setting\HotSearchLogic;

class HotSearchController extends BaseAdminController
{
    public function getConfig()
    {
        return $this->data(HotSearchLogic::getConfig());
    }

    public function setConfig()
    {
        $r = HotSearchLogic::setConfig($this->request->post());
        return $r ? $this->success('操作成功') : $this->fail(HotSearchLogic::getError());
    }
}
