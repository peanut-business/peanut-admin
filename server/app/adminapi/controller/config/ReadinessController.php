<?php
declare(strict_types=1);

namespace app\adminapi\controller\config;

use app\adminapi\controller\BaseAdminController;
use app\common\service\member\MemberTenantContext;
use app\common\service\readiness\FirstRunReadinessHost;
use think\response\Json;

final class ReadinessController extends BaseAdminController
{
    public function checklist(): Json
    {
        return $this->data((new FirstRunReadinessHost())->checklist(
            MemberTenantContext::member(),
            (string)$this->request->domain(),
            (string)config('deployment.mode'),
        ));
    }
}
