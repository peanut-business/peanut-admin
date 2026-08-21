<?php
declare(strict_types=1);

namespace app\adminapi\controller;

use app\adminapi\logic\WorkbenchLogic;
use app\common\service\org\OrgTenantContext;

class WorkbenchController extends BaseAdminController
{
    public function index()
    {
        return $this->data(WorkbenchLogic::index(OrgTenantContext::member($this->request)));
    }
}
