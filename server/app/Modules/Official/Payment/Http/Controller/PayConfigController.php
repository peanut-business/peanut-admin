<?php
declare(strict_types=1);

namespace app\Modules\Official\Payment\Http\Controller;

use think\App;

use app\adminapi\controller\BaseAdminController;
use app\Modules\Official\Payment\Application\PayConfigApplicationService;
use app\Modules\Official\Payment\Validation\PayConfigValidate;
use app\common\service\member\MemberTenantContext;

class PayConfigController extends BaseAdminController
{
    public function __construct(App $app, private readonly PayConfigApplicationService $payConfigs)
    {
        parent::__construct($app);
    }

    public function getConfig()
    {
        return $this->data($this->payConfigs->getConfig(MemberTenantContext::member()));
    }

    public function setConfig()
    {
        $params = $this->request->post();
        $this->validate($params, PayConfigValidate::class);
        $result = $this->payConfigs->setConfig(MemberTenantContext::member(), $params);
        return $result ? $this->success('操作成功') : $this->fail($this->payConfigs->getError());
    }
}
