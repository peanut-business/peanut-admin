<?php
declare(strict_types=1);

namespace app\adminapi\controller\config;

use app\adminapi\controller\BaseAdminController;
use app\common\service\member\MemberTenantContext;
use app\common\service\readiness\FirstRunReadinessHost;
use think\App;
use think\response\Json;

final class ReadinessController extends BaseAdminController
{
    public function __construct(App $app, private readonly FirstRunReadinessHost $readiness)
    {
        parent::__construct($app);
    }

    public function checklist(): Json
    {
        return $this->data($this->readiness->checklist(
            MemberTenantContext::member(),
            (string)$this->request->domain(),
            (string)config('deployment.mode'),
        ));
    }
}
