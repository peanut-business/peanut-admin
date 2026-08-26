<?php
declare(strict_types=1);

namespace app\Modules\Official\Payment\Http\Controller;

use app\adminapi\controller\BaseAdminController;
use app\Modules\Official\Payment\Service\RechargeSettingLogic;
use app\Modules\Official\Payment\Validation\RechargeSettingValidate;
use app\common\service\finance\FinanceTenantContext;

class RechargeSettingController extends BaseAdminController
{
    public function config()
    {
        return $this->data(RechargeSettingLogic::getConfig(FinanceTenantContext::member($this->request)));
    }

    public function save()
    {
        $params = $this->request->post();
        $this->validate($params, RechargeSettingValidate::class . '.save');
        $result = RechargeSettingLogic::save(FinanceTenantContext::member($this->request), $params);
        return $result
            ? $this->success('保存成功')
            : $this->fail(RechargeSettingLogic::getError());
    }
}
