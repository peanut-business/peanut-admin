<?php
declare(strict_types=1);

namespace app\modules\official\payment\controller;

use think\App;
use app\common\execution\CurrentExecutionContext;

use app\adminapi\controller\BaseAdminController;
use app\modules\official\payment\services\PayConfigApplicationService;
use app\modules\official\payment\validate\PayConfigValidate;

class PayConfigController extends BaseAdminController
{
    public function __construct(App $app, CurrentExecutionContext $executionContext, private readonly PayConfigApplicationService $payConfigs)
    {
        parent::__construct($app, $executionContext);
    }

    public function getConfig()
    {
        return $this->data($this->payConfigs->getConfig($this->tenantAdminContext()));
    }

    public function setConfig()
    {
        $params = $this->request->post();
        $this->validate($params, PayConfigValidate::class);
        $this->payConfigs->setConfig($this->tenantAdminContext(), $params);
        return $this->success('操作成功');
    }
}
