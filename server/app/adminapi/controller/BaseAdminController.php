<?php
declare(strict_types=1);

namespace app\adminapi\controller;

use app\common\controller\BaseLikeAdminController;
use app\common\execution\CurrentExecutionContext;
use app\common\execution\AdminExecutionContext;
use think\App;

class BaseAdminController extends BaseLikeAdminController
{
    protected int   $adminId   = 0;
    protected array $adminInfo = [];

    public function __construct(
        App $app,
        protected readonly CurrentExecutionContext $executionContext,
    ) {
        parent::__construct($app);
    }

    public function initialize(): void
    {
        $current = $this->executionContext;
        if ($current->current() instanceof AdminExecutionContext) {
            $this->adminInfo = $current->tenantAdminPrincipal();
            $this->adminId = (int)$this->adminInfo['id'];
        }
    }

    protected function executionContext(): CurrentExecutionContext
    {
        return $this->executionContext;
    }

    protected function tenantAdminContext(): \PeanutAdmin\Kernel\Auth\TenantContext
    {
        return $this->executionContext->tenantAdmin();
    }
}
