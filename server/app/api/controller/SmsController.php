<?php
declare(strict_types=1);

namespace app\api\controller;

use app\api\logic\SmsLogic;
use app\api\validate\SmsValidate;
use app\common\service\notice\NoticeTenantContext;

class SmsController extends BaseApiController
{
    public array $notNeedLogin = ['sendCode'];

    public function sendCode()
    {
        $params = $this->request->post();
        $this->validate($params, SmsValidate::class . '.send');
        $result = SmsLogic::sendCode(
            NoticeTenantContext::verification($this->request, 'notice.verification.send'),
            $params
        );
        return $result
            ? $this->success('发送成功')
            : $this->fail(SmsLogic::getError());
    }
}
