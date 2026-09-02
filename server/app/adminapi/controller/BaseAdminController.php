<?php
declare(strict_types=1);

namespace app\adminapi\controller;

use app\BaseController;
use app\common\traits\ApiResponseTrait;
use app\common\execution\CurrentExecutionContext;
use app\common\execution\AdminExecutionContext;
use PeanutAdmin\Kernel\Auth\TenantContext;
use think\App;

abstract class BaseAdminController extends BaseController
{
    use ApiResponseTrait;

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
            $this->adminId = (int)($this->adminInfo['id'] ?? 0);
        }
    }

    protected function executionContext(): CurrentExecutionContext
    {
        return $this->executionContext;
    }

    protected function tenantAdminContext(): TenantContext
    {
        return $this->executionContext->tenantAdmin();
    }
}
