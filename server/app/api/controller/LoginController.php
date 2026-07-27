<?php
declare(strict_types=1);

namespace app\api\controller;

use app\api\logic\LoginLogic;

class LoginController extends BaseApiController
{
    public array $notNeedLogin = ['register', 'account', 'logout'];

    /** 注册账号 */
    public function register()
    {
        $params = [
            'account'  => $this->request->post('account/s', ''),
            'password' => $this->request->post('password/s', ''),
        ];

        if (empty($params['account']) || empty($params['password'])) {
            return $this->fail('账号和密码不能为空');
        }

        $result = LoginLogic::register($params);
        if ($result === false) {
            return $this->fail(LoginLogic::getError());
        }

        return $this->success('注册成功');
    }

    /** 账号/手机号 + 密码登录 */
    public function account()
    {
        $params = [
            'account'  => $this->request->post('account/s', ''),
            'password' => $this->request->post('password/s', ''),
            'terminal' => $this->request->post('terminal/d', 1),
        ];

        if (empty($params['account']) || empty($params['password'])) {
            return $this->fail('账号和密码不能为空');
        }

        $result = LoginLogic::login($params);
        if ($result === false) {
            return $this->fail(LoginLogic::getError());
        }

        return $this->data($result);
    }

    /** 退出登录 */
    public function logout()
    {
        LoginLogic::logout();
        return $this->success('退出成功');
    }
}
