<?php
declare(strict_types=1);

namespace app\adminapi\controller\setting;

use think\App;
use app\common\execution\CurrentExecutionContext;

use app\adminapi\controller\BaseAdminController;
use app\adminapi\application\setting\HotSearchApplicationService;

class HotSearchController extends BaseAdminController
{
    public function __construct(App $app, CurrentExecutionContext $executionContext, private readonly HotSearchApplicationService $hotSearch)
    {
        parent::__construct($app, $executionContext);
    }

    public function getConfig()
    {
        return $this->data($this->hotSearch->getConfig($this->tenantAdminContext()));
    }

    public function setConfig()
    {
        $this->hotSearch->setConfig(
            $this->tenantAdminContext(),
            $this->request->post()
        );
        return $this->success('操作成功');
    }
}
