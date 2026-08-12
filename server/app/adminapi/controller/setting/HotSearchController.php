<?php
declare(strict_types=1);

namespace app\adminapi\controller\setting;

use app\adminapi\controller\BaseAdminController;
use app\adminapi\logic\setting\HotSearchLogic;
use app\common\service\hot_search\HotSearchTenantContext;

class HotSearchController extends BaseAdminController
{
    public function getConfig()
    {
        return $this->data(HotSearchLogic::getConfig(HotSearchTenantContext::member($this->request)));
    }

    public function setConfig()
    {
        $r = HotSearchLogic::setConfig(
            HotSearchTenantContext::member($this->request),
            $this->request->post()
        );
        return $r ? $this->success('操作成功') : $this->fail(HotSearchLogic::getError());
    }
}
