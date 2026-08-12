<?php
declare(strict_types=1);

namespace app\api\controller;

use app\api\logic\LoginLogic;
use app\common\service\notice\NoticeTenantContext;

class LoginController extends BaseApiController
{
    public array $notNeedLogin = ['register', 'account', 'mobile', 'resetPassword', 'logout'];

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

    /** 手机号验证码登录 */
    public function mobile()
    {
        $params = [
            'mobile' => $this->request->post('mobile/s', ''),
            'code'   => $this->request->post('code/s', ''),
        ];
        if (!preg_match('/^1[3-9]\d{9}$/', $params['mobile']) || $params['code'] === '') {
            return $this->fail('手机号或验证码格式不正确');
        }

        $result = LoginLogic::mobileLogin(
            NoticeTenantContext::verification($this->request, 'notice.verification.verify'),
            $params
        );
        return $result === false
            ? $this->fail(LoginLogic::getError())
            : $this->data($result);
    }

    /** 手机号验证码找回密码 */
    public function resetPassword()
    {
        $params = [
            'mobile'   => $this->request->post('mobile/s', ''),
            'code'     => $this->request->post('code/s', ''),
            'password' => $this->request->post('password/s', ''),
        ];
        if (!preg_match('/^1[3-9]\d{9}$/', $params['mobile'])
            || $params['code'] === '' || strlen($params['password']) < 6) {
            return $this->fail('手机号、验证码或新密码格式不正确');
        }

        return LoginLogic::resetPassword(
            NoticeTenantContext::verification($this->request, 'notice.verification.verify'),
            $params
        )
            ? $this->success('密码已重置')
            : $this->fail(LoginLogic::getError());
    }

    /** 退出登录 */
    public function logout()
    {
        LoginLogic::logout();
        return $this->success('退出成功');
    }
}
