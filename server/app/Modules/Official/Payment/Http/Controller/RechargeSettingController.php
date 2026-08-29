<?php
declare(strict_types=1);

namespace app\Modules\Official\Payment\Http\Controller;

use think\App;

use app\adminapi\controller\BaseAdminController;
use app\Modules\Official\Payment\Application\RechargeSettingApplicationService;
use app\Modules\Official\Payment\Validation\RechargeSettingValidate;
use app\common\service\finance\FinanceTenantContext;

class RechargeSettingController extends BaseAdminController
{
    public function __construct(App $app, private readonly RechargeSettingApplicationService $rechargeSettings)
    {
        parent::__construct($app);
    }

    public function config()
    {
        return $this->data($this->rechargeSettings->getConfig(FinanceTenantContext::member()));
    }

    public function save()
    {
        $params = $this->request->post();
        $this->validate($params, RechargeSettingValidate::class . '.save');
        $result = $this->rechargeSettings->save(FinanceTenantContext::member(), $params);
        return $result
            ? $this->success('保存成功')
            : $this->fail($this->rechargeSettings->getError());
    }
}
