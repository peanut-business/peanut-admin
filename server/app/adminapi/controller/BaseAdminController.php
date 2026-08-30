<?php
declare(strict_types=1);

namespace app\adminapi\controller;

use app\common\controller\BaseLikeAdminController;
use app\common\execution\CurrentExecutionContext;
use app\common\execution\ExecutionContext;

class BaseAdminController extends BaseLikeAdminController
{
    protected int   $adminId   = 0;
    protected array $adminInfo = [];

    public function initialize(): void
    {
        $current = app(CurrentExecutionContext::class);
        if ($current->current()?->actorType === ExecutionContext::TENANT_ADMIN) {
            $this->adminInfo = $current->tenantAdminPrincipal();
            $this->adminId = (int)$this->adminInfo['id'];
        }
    }
}
