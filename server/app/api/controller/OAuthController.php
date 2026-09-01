<?php
declare(strict_types=1);

namespace app\api\controller;

use app\Modules\Official\Oauth\Contracts\OAuthCommands;
use app\api\validate\OAuthValidate;
use app\common\service\oauth\OAuthBrowserCallbackService;
use app\common\service\external\ExternalTenantResolver;
use app\common\service\module\ModuleExecutionBoundary;
use app\common\execution\ExecutionContextStore;
use app\common\http\RequestTrace;
use app\common\application\BusinessException;
use think\App;
use app\common\execution\CurrentExecutionContext;

class OAuthController extends BaseApiController
{
    public function __construct(
        App $app,
        CurrentExecutionContext $executionContext,
        private readonly OAuthCommands $commands,
        private readonly ExecutionContextStore $executionContexts,
        private readonly ModuleExecutionBoundary $modules,
    ) {
        parent::__construct($app, $executionContext);
    }

    public function begin()
    {
        $params = $this->request->post();
        $this->validate($params, OAuthValidate::class . '.begin');
        $scene = (string)$params['scene'];
        if (!in_array($scene, ['oa', 'open_pc'], true)) {
            throw BusinessException::invalid('OAUTH_SCENE_UNSUPPORTED', '该微信场景不支持浏览器授权');
        }
        $callbackUrl = OAuthBrowserCallbackService::callbackUrl(
            (string)$this->request->domain(),
            $scene
        );
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
        $result = $this->executionContexts->run(
                new \app\common\execution\SystemExecutionContext($resolution->context),
                function () use ($resolution, $scene, $params, $callbackUrl) {
                    $this->modules->assertExternalCallback('official.oauth');
                    return $this->commands->begin(
                        $resolution->context,
                        $scene,
                        (string)$params['return_path'],
                        $callbackUrl,
                        $resolution->binding,
                    );
                },
        );
        return $this->data($result);
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
        $resolution = ExternalTenantResolver::production()->oauthState(
                ExternalTenantResolver::oauthProvider((string)$params['scene']),
                (string)$params['state'],
                $this->operationId(),
            );
        $result = $this->executionContexts->run(
                new \app\common\execution\SystemExecutionContext($resolution->context),
                function () use ($resolution, $params) {
                    $this->modules->assertExternalCallback('official.oauth');
                    return $this->commands->callback(
                        $resolution->context,
                        (string)$params['scene'],
                        (string)$params['code'],
                        (string)$params['state'],
                        $resolution->binding,
                    );
                },
        );
        return $this->data($result);
    }

    public function miniProgram()
    {
        $params = $this->request->post();
        $this->validate($params, OAuthValidate::class . '.mnp');
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
        $result = $this->executionContexts->run(
                new \app\common\execution\SystemExecutionContext($resolution->context),
                function () use ($resolution, $params) {
                    $this->modules->assertExternalCallback('official.oauth');
                    return $this->commands->miniProgramLogin(
                        $resolution->context,
                        (string)$params['code'],
                        $resolution->binding,
                    );
                },
        );
        return $this->data($result);
    }

    public function complete()
    {
        $params = $this->request->post();
        $this->validate($params, OAuthValidate::class . '.complete');
        $params['code'] = (string)($params['verification_code'] ?? '');
        $resolution = ExternalTenantResolver::production()->oauthTicket(
                (string)$params['ticket'],
                $this->operationId(),
            );
        $result = $this->executionContexts->run(
                new \app\common\execution\SystemExecutionContext($resolution->context),
                function () use ($resolution, $params) {
                    $this->modules->assertExternalCallback('official.oauth');
                    return $this->commands->complete($resolution->context, $params);
                },
        );
        return $this->data($result);
    }

    public function bind()
    {
        $params = $this->request->post();
        $this->validate($params, OAuthValidate::class . '.bind');
        $this->commands->bind(
            $this->memberContext(),
            $this->memberId,
            (string)$params['scene'],
            (string)$params['code']
        );
        return $this->success('绑定成功');
    }

    private function operationId(): string
    {
        return RequestTrace::id($this->request, 'oauth');
    }

}
