<?php
declare(strict_types=1);

namespace app\api\controller;

use think\App;
use app\common\execution\CurrentExecutionContext;

use app\api\application\SmsApplicationService;
use app\api\validate\SmsValidate;

class SmsController extends BaseApiController
{
    public function __construct(App $app, CurrentExecutionContext $executionContext, private readonly SmsApplicationService $sms)
    {
        parent::__construct($app, $executionContext);
    }

    public array $notNeedLogin = ['sendCode'];

    public function sendCode()
    {
        $params = $this->request->post();
        $this->validate($params, SmsValidate::class . '.send');
        $this->sms->sendCode(
            $this->publicTenantContext('notice.verification.send'),
            $params
        );
        return $this->success('发送成功');
    }
}
