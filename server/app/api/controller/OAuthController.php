<?php
declare(strict_types=1);

namespace app\api\controller;

use app\api\logic\OAuthLogic;
use app\api\validate\OAuthValidate;

class OAuthController extends BaseApiController
{
    public function begin()
    {
        $params = $this->request->post();
        $this->validate($params, OAuthValidate::class . '.begin');
        $callbackUrl = rtrim((string)$this->request->domain(), '/') . '/oauth/callback';
        $result = OAuthLogic::begin(
            (string)$params['scene'],
            (string)$params['return_path'],
            $callbackUrl
        );
        return $result === false ? $this->fail(OAuthLogic::getError()) : $this->data($result);
    }

    public function callback()
    {
        $params = $this->request->post();
        $this->validate($params, OAuthValidate::class . '.callback');
        $result = OAuthLogic::callback(
            (string)$params['scene'],
            (string)$params['code'],
            (string)$params['state']
        );
        return $result === false ? $this->fail(OAuthLogic::getError()) : $this->data($result);
    }

    public function miniProgram()
    {
        $params = $this->request->post();
        $this->validate($params, OAuthValidate::class . '.mnp');
        $result = OAuthLogic::miniProgramLogin((string)$params['code']);
        return $result === false ? $this->fail(OAuthLogic::getError()) : $this->data($result);
    }

    public function complete()
    {
        $params = $this->request->post();
        $this->validate($params, OAuthValidate::class . '.complete');
        $params['code'] = (string)($params['verification_code'] ?? '');
        $result = OAuthLogic::complete($params);
        return $result === false ? $this->fail(OAuthLogic::getError()) : $this->data($result);
    }

    public function bind()
    {
        $params = $this->request->post();
        $this->validate($params, OAuthValidate::class . '.bind');
        $result = OAuthLogic::bind(
            $this->memberId,
            (string)$params['scene'],
            (string)$params['code']
        );
        return $result ? $this->success('绑定成功') : $this->fail(OAuthLogic::getError());
    }
}
