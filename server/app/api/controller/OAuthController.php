<?php
declare(strict_types=1);

namespace app\api\controller;

use app\Modules\Official\Oauth\Contracts\OAuthCommands;
use app\api\validate\OAuthValidate;
use app\common\service\oauth\OAuthBrowserCallbackService;
use app\common\service\member\MemberTenantContext;
use app\common\service\external\ExternalTenantResolver;
use app\common\service\module\ModuleExecutionBoundary;
use app\common\execution\ExecutionContext;
use app\common\execution\ExecutionContextStore;
use app\common\http\RequestTrace;
use think\App;

class OAuthController extends BaseApiController
{
    public function __construct(
        App $app,
        private readonly OAuthCommands $commands,
    ) {
        parent::__construct($app);
    }

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
            $result = app(ExecutionContextStore::class)->run(
                ExecutionContext::system($resolution->context),
                function () use ($resolution, $scene, $params, $callbackUrl) {
                    app(ModuleExecutionBoundary::class)->assertExternalCallback('official.oauth');
                    return $this->commands->begin(
                        $resolution->context,
                        $scene,
                        (string)$params['return_path'],
                        $callbackUrl,
                        $resolution->binding,
                    );
                },
            );
        } catch (\Throwable) {
            return $this->fail('微信授权请求无效');
        }
        return $result === false ? $this->fail($this->commands->error()) : $this->data($result);
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
            $result = app(ExecutionContextStore::class)->run(
                ExecutionContext::system($resolution->context),
                function () use ($resolution, $params) {
                    app(ModuleExecutionBoundary::class)->assertExternalCallback('official.oauth');
                    return $this->commands->callback(
                        $resolution->context,
                        (string)$params['scene'],
                        (string)$params['code'],
                        (string)$params['state'],
                        $resolution->binding,
                    );
                },
            );
        } catch (\Throwable) {
            return $this->fail('微信授权请求无效');
        }
        return $result === false ? $this->fail($this->commands->error()) : $this->data($result);
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
            $result = app(ExecutionContextStore::class)->run(
                ExecutionContext::system($resolution->context),
                function () use ($resolution, $params) {
                    app(ModuleExecutionBoundary::class)->assertExternalCallback('official.oauth');
                    return $this->commands->miniProgramLogin(
                        $resolution->context,
                        (string)$params['code'],
                        $resolution->binding,
                    );
                },
            );
        } catch (\Throwable) {
            return $this->fail('微信授权请求无效');
        }
        return $result === false ? $this->fail($this->commands->error()) : $this->data($result);
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
            $result = app(ExecutionContextStore::class)->run(
                ExecutionContext::system($resolution->context),
                function () use ($resolution, $params) {
                    app(ModuleExecutionBoundary::class)->assertExternalCallback('official.oauth');
                    return $this->commands->complete($resolution->context, $params);
                },
            );
        } catch (\Throwable) {
            return $this->fail('微信授权请求无效');
        }
        return $result === false ? $this->fail($this->commands->error()) : $this->data($result);
    }

    public function bind()
    {
        $params = $this->request->post();
        $this->validate($params, OAuthValidate::class . '.bind');
        $result = $this->commands->bind(
            MemberTenantContext::member(),
            $this->memberId,
            (string)$params['scene'],
            (string)$params['code']
        );
        return $result ? $this->success('绑定成功') : $this->fail($this->commands->error());
    }

    private function operationId(): string
    {
        return RequestTrace::id($this->request, 'oauth');
    }

}
