<?php
declare(strict_types=1);

namespace app\api\controller;

use app\api\logic\OAuthLogic;
use app\api\validate\OAuthValidate;
use app\common\service\oauth\OAuthBrowserCallbackService;
use app\common\service\member\MemberTenantContext;
use app\common\service\external\ExternalTenantResolver;
use app\common\service\module\ModuleExecutionContext;
use app\common\service\module\ModuleExecutionGuard;
use PDO;
use think\facade\Db;

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
        try {
            $provider = ExternalTenantResolver::oauthProvider($scene);
            $clientId = trim((string)($params['client_id'] ?? ''));
            $resolution = $clientId === ''
                ? ExternalTenantResolver::production()->onlyActiveBinding(
                    $provider,
                    'oauth.begin',
                    $this->operationId(),
                )
                : ExternalTenantResolver::production()->clientIdentity(
                    $provider,
                    $clientId,
                    'oauth.begin',
                    $this->operationId(),
                );
            $this->assertModule($resolution->context);
            $result = OAuthLogic::begin(
                $resolution->context,
                $scene,
                (string)$params['return_path'],
                $callbackUrl,
                $resolution->binding,
            );
        } catch (\Throwable) {
            return $this->fail('微信授权请求无效');
        }
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
        try {
            $resolution = ExternalTenantResolver::production()->oauthState(
                ExternalTenantResolver::oauthProvider((string)$params['scene']),
                (string)$params['state'],
                $this->operationId(),
            );
            $this->assertModule($resolution->context);
            $result = OAuthLogic::callback(
                $resolution->context,
                (string)$params['scene'],
                (string)$params['code'],
                (string)$params['state'],
                $resolution->binding,
            );
        } catch (\Throwable) {
            return $this->fail('微信授权请求无效');
        }
        return $result === false ? $this->fail(OAuthLogic::getError()) : $this->data($result);
    }

    public function miniProgram()
    {
        $params = $this->request->post();
        $this->validate($params, OAuthValidate::class . '.mnp');
        try {
            $resolver = ExternalTenantResolver::production();
            $clientId = trim((string)($params['client_id'] ?? ''));
            $resolution = $clientId === ''
                ? $resolver->onlyActiveBinding(
                    ExternalTenantResolver::WECHAT_MINI_PROGRAM,
                    'oauth.mini-program',
                    $this->operationId(),
                )
                : $resolver->clientIdentity(
                    ExternalTenantResolver::WECHAT_MINI_PROGRAM,
                    $clientId,
                    'oauth.mini-program',
                    $this->operationId(),
                );
            $this->assertModule($resolution->context);
            $result = OAuthLogic::miniProgramLogin(
                $resolution->context,
                (string)$params['code'],
                $resolution->binding,
            );
        } catch (\Throwable) {
            return $this->fail('微信授权请求无效');
        }
        return $result === false ? $this->fail(OAuthLogic::getError()) : $this->data($result);
    }

    public function complete()
    {
        $params = $this->request->post();
        $this->validate($params, OAuthValidate::class . '.complete');
        $params['code'] = (string)($params['verification_code'] ?? '');
        try {
            $resolution = ExternalTenantResolver::production()->oauthTicket(
                (string)$params['ticket'],
                $this->operationId(),
            );
            $this->assertModule($resolution->context);
            $result = OAuthLogic::complete($resolution->context, $params);
        } catch (\Throwable) {
            return $this->fail('微信授权请求无效');
        }
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

    private function operationId(): string
    {
        $requestId = trim((string)$this->request->header('X-Request-Id', ''));
        return $requestId !== '' ? $requestId : bin2hex(random_bytes(16));
    }

    private function assertModule(\PeanutAdmin\Kernel\Context\TenantSystemContext $context): void
    {
        $pdo = Db::connect()->connect();
        if (!$pdo instanceof PDO) {
            throw new \RuntimeException('OAUTH_MODULE_DATABASE_UNAVAILABLE');
        }
        (new ModuleExecutionGuard($pdo, 'official.oauth'))->assertEnabled(
            ModuleExecutionContext::system('official.oauth', $context),
        );
    }
}
