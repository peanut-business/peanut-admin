<?php
declare(strict_types=1);

namespace app\api\controller;

use think\App;
use app\common\execution\CurrentExecutionContext;

use app\api\application\LoginApplicationService;
use app\common\service\notice\NoticeTenantContext;
use app\common\application\BusinessException;

class LoginController extends BaseApiController
{
    public function __construct(App $app, CurrentExecutionContext $executionContext, private readonly LoginApplicationService $login)
    {
        parent::__construct($app, $executionContext);
    }

    public array $notNeedLogin = ['register', 'account', 'mobile', 'resetPassword', 'logout'];

    /** 注册账号 */
    public function register()
    {
        $params = [
            'account'  => $this->request->post('account/s', ''),
            'password' => $this->request->post('password/s', ''),
        ];

        if (empty($params['account']) || empty($params['password'])) {
            throw BusinessException::invalid('MEMBER_CREDENTIALS_REQUIRED', '账号和密码不能为空');
        }

        $this->login->register($this->publicTenantContext('member.register'), $params);
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
            throw BusinessException::invalid('MEMBER_CREDENTIALS_REQUIRED', '账号和密码不能为空');
        }

        return $this->data($this->login->login(
            $this->publicTenantContext('member.login'),
            $params,
            $this->request->ip(),
        ));
    }

    /** 手机号验证码登录 */
    public function mobile()
    {
        $params = [
            'mobile' => $this->request->post('mobile/s', ''),
            'code'   => $this->request->post('code/s', ''),
        ];
        if (!preg_match('/^1[3-9]\d{9}$/', $params['mobile']) || $params['code'] === '') {
            throw BusinessException::invalid('MEMBER_MOBILE_LOGIN_INVALID', '手机号或验证码格式不正确');
        }

        return $this->data($this->login->mobileLogin(
            NoticeTenantContext::verification($this->request, 'notice.verification.verify'),
            $params,
            $this->request->ip(),
        ));
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
            throw BusinessException::invalid('MEMBER_PASSWORD_RESET_INVALID', '手机号、验证码或新密码格式不正确');
        }

        $this->login->resetPassword(
            NoticeTenantContext::verification($this->request, 'notice.verification.verify'),
            $params
        );
        return $this->success('密码已重置');
    }

    /** 退出登录 */
    public function logout()
    {
        $this->login->logout();
        return $this->success('退出成功');
    }
}
