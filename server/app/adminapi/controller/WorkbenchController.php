<?php
declare(strict_types=1);

namespace app\adminapi\controller;

use think\App;

use app\adminapi\application\WorkbenchApplicationService;
use app\common\service\org\OrgTenantContext;

class WorkbenchController extends BaseAdminController
{
    public function __construct(App $app, private readonly WorkbenchApplicationService $workbench)
    {
        parent::__construct($app);
    }

    public function index()
    {
        return $this->data($this->workbench->index(OrgTenantContext::member()));
    }
}
