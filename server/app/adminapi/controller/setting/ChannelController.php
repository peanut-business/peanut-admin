<?php
declare(strict_types=1);

namespace app\adminapi\controller\setting;

use app\adminapi\controller\BaseAdminController;
use app\adminapi\logic\setting\ChannelLogic;

class ChannelController extends BaseAdminController
{
    public function getConfig()
    {
        return $this->data(ChannelLogic::getConfig());
    }

    public function setConfig()
    {
        ChannelLogic::setConfig($this->request->post());
        return $this->success('操作成功');
    }
}
