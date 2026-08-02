<?php
declare(strict_types=1);

namespace app\api\controller;

use app\api\logic\SmsLogic;
use app\api\validate\SmsValidate;

class SmsController extends BaseApiController
{
    public array $notNeedLogin = ['sendCode'];

    public function sendCode()
    {
        $params = $this->request->post();
        $this->validate($params, SmsValidate::class . '.send');
        $result = SmsLogic::sendCode($params);
        return $result
            ? $this->success('发送成功')
            : $this->fail(SmsLogic::getError());
    }
}
