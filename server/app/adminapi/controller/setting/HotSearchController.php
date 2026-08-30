<?php
declare(strict_types=1);

namespace app\adminapi\controller\setting;

use think\App;

use app\adminapi\controller\BaseAdminController;
use app\adminapi\application\setting\HotSearchApplicationService;
use app\common\service\hot_search\HotSearchTenantContext;

class HotSearchController extends BaseAdminController
{
    public function __construct(App $app, private readonly HotSearchApplicationService $hotSearch)
    {
        parent::__construct($app);
    }

    public function getConfig()
    {
        return $this->data($this->hotSearch->getConfig(HotSearchTenantContext::member()));
    }

    public function setConfig()
    {
        $r = $this->hotSearch->setConfig(
            HotSearchTenantContext::member(),
            $this->request->post()
        );
        return $r ? $this->success('操作成功') : $this->fail($this->hotSearch->getError());
    }
}
