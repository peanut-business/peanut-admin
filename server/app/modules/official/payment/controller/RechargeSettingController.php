<?php
declare(strict_types=1);

namespace app\modules\official\payment\controller;

use think\App;
use app\common\execution\CurrentExecutionContext;

use app\adminapi\controller\BaseAdminController;
use app\modules\official\payment\services\RechargeSettingApplicationService;
use app\modules\official\payment\validate\RechargeSettingValidate;

class RechargeSettingController extends BaseAdminController
{
    public function __construct(App $app, CurrentExecutionContext $executionContext, private readonly RechargeSettingApplicationService $rechargeSettings)
    {
        parent::__construct($app, $executionContext);
    }

    public function config()
    {
        return $this->data($this->rechargeSettings->getConfig($this->tenantAdminContext()));
    }

    public function save()
    {
        $params = $this->request->post();
        $this->validate($params, RechargeSettingValidate::class . '.save');
        $this->rechargeSettings->save($this->tenantAdminContext(), $params);
        return $this->success('保存成功');
    }
}
