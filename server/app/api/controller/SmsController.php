<?php
declare(strict_types=1);

namespace app\api\controller;

use think\App;

use app\api\application\SmsApplicationService;
use app\api\validate\SmsValidate;
use app\common\service\notice\NoticeTenantContext;

class SmsController extends BaseApiController
{
    public function __construct(App $app, private readonly SmsApplicationService $sms)
    {
        parent::__construct($app);
    }

    public array $notNeedLogin = ['sendCode'];

    public function sendCode()
    {
        $params = $this->request->post();
        $this->validate($params, SmsValidate::class . '.send');
        $result = $this->sms->sendCode(
            NoticeTenantContext::verification($this->request, 'notice.verification.send'),
            $params
        );
        return $result
            ? $this->success('发送成功')
            : $this->fail($this->sms->getError());
    }
}
