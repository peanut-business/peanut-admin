<?php
declare(strict_types=1);

namespace app\adminapi\controller\config;

use app\adminapi\controller\BaseAdminController;
use app\common\service\readiness\FirstRunReadinessHost;
use think\App;
use app\common\execution\CurrentExecutionContext;
use think\response\Json;

final class ReadinessController extends BaseAdminController
{
    public function __construct(App $app, CurrentExecutionContext $executionContext, private readonly FirstRunReadinessHost $readiness)
    {
        parent::__construct($app, $executionContext);
    }

    public function checklist(): Json
    {
        return $this->data($this->readiness->checklist(
            $this->tenantAdminContext(),
            (string)$this->request->domain(),
            (string)config('deployment.mode'),
        ));
    }
}
