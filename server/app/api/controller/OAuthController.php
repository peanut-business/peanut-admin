<?php
declare(strict_types=1);

namespace app\api\controller;

use app\api\logic\OAuthLogic;
use app\api\validate\OAuthValidate;
use app\common\service\oauth\OAuthBrowserCallbackService;
use app\common\service\member\MemberTenantContext;
use app\common\service\notice\NoticeTenantContext;

class OAuthController extends BaseApiController
{
    public function begin()
    {
        $params = $this->request->post();
        $this->validate($params, OAuthValidate::class . '.begin');
        $scene = (string)$params['scene'];
        if (!in_array($scene, ['oa', 'open_pc'], true)) {
            return $this->fail('该微信场景不支持浏览器授权');
        }
        $callbackUrl = OAuthBrowserCallbackService::callbackUrl(
            (string)$this->request->domain(),
            $scene
        );
        $result = OAuthLogic::begin(
            MemberTenantContext::system($this->request, 'member.oauth-begin'),
            $scene,
            (string)$params['return_path'],
            $callbackUrl
        );
        return $result === false ? $this->fail(OAuthLogic::getError()) : $this->data($result);
    }

    public function redirectPc()
    {
        return redirect(OAuthBrowserCallbackService::clientRedirectUrl(
            'pc',
            $this->request->get()
        ));
    }

    public function redirectOfficialAccount()
    {
        return redirect(OAuthBrowserCallbackService::clientRedirectUrl(
            'official-account',
            $this->request->get()
        ));
    }

    public function callback()
    {
        $params = $this->request->post();
        $this->validate($params, OAuthValidate::class . '.callback');
        $result = OAuthLogic::callback(
            MemberTenantContext::system($this->request, 'member.oauth-callback'),
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
        $result = OAuthLogic::miniProgramLogin(
            MemberTenantContext::system($this->request, 'member.oauth-mini-program'),
            (string)$params['code']
        );
        return $result === false ? $this->fail(OAuthLogic::getError()) : $this->data($result);
    }

    public function complete()
    {
        $params = $this->request->post();
        $this->validate($params, OAuthValidate::class . '.complete');
        $params['code'] = (string)($params['verification_code'] ?? '');
        $result = OAuthLogic::complete(
            NoticeTenantContext::verification($this->request, 'notice.verification.verify'),
            $params
        );
        return $result === false ? $this->fail(OAuthLogic::getError()) : $this->data($result);
    }

    public function bind()
    {
        $params = $this->request->post();
        $this->validate($params, OAuthValidate::class . '.bind');
        $result = OAuthLogic::bind(
            MemberTenantContext::member($this->request),
            $this->memberId,
            (string)$params['scene'],
            (string)$params['code']
        );
        return $result ? $this->success('绑定成功') : $this->fail(OAuthLogic::getError());
    }
}
