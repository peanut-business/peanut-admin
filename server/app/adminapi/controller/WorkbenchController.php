<?php
declare(strict_types=1);

namespace app\adminapi\controller;

use think\App;
use app\common\execution\CurrentExecutionContext;

use app\adminapi\application\WorkbenchApplicationService;
use app\common\service\org\OrgTenantContext;

class WorkbenchController extends BaseAdminController
{
    public function __construct(App $app, CurrentExecutionContext $executionContext, private readonly WorkbenchApplicationService $workbench)
    {
        parent::__construct($app, $executionContext);
    }

    public function index()
    {
        return $this->data($this->workbench->index(OrgTenantContext::member()));
    }
}
