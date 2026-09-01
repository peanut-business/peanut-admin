<?php
declare(strict_types=1);

namespace app\platform\controller;

use app\common\execution\CurrentExecutionContext;
use app\platform\http\PlatformRequest;
use app\platform\service\PlatformOperatorSessionService;
use app\platform\validate\PlatformLoginValidate;
use PeanutAdmin\Kernel\Auth\AuthException;
use PeanutAdmin\Kernel\Auth\PlatformRefreshCookie;
use think\App;

final class PlatformSessionController extends BasePlatformController
{
    public function __construct(
        App $app,
        CurrentExecutionContext $execution,
        private readonly PlatformOperatorSessionService $sessions,
    ) {
        parent::__construct($app, $execution);
    }

    public function login()
    {
        $params = $this->request->post();
        $this->validate($params, PlatformLoginValidate::class);
        try {
            $authentication = $this->sessions->login(
                trim((string)$params['email']),
                (string)$params['password'],
                $this->request->ip(),
                $this->request->header('User-Agent'),
                $this->requestId()
            );
        } catch (AuthException|\DomainException|\InvalidArgumentException) {
            return $this->fail('Email or password is incorrect.');
        }

        return $this->data($authentication->responseData())
            ->header(['Set-Cookie' => PlatformRefreshCookie::issue($authentication->tokens->refresh)]);
    }

    public function refresh()
    {
        $token = PlatformRequest::refreshToken($this->request);
        try {
            $authentication = $this->sessions->refresh(
                $token,
                $this->request->ip(),
                $this->request->header('User-Agent'),
                $this->requestId()
            );
        } catch (AuthException|\DomainException|\InvalidArgumentException) {
            throw \app\common\http\ApiProblem::fromEnvelope(
                'Platform refresh credential is invalid.',
                ['error_code' => 'PLATFORM_REFRESH_CREDENTIAL_INVALID'],
                40100,
            )->withHeaders(['Set-Cookie' => PlatformRefreshCookie::clear()]);
        }

        return $this->data($authentication->responseData())
            ->header(['Set-Cookie' => PlatformRefreshCookie::issue($authentication->tokens->refresh)]);
    }

    public function logout()
    {
        $token = PlatformRequest::bearerToken($this->request);
        if ($token !== '') {
            try {
                $this->sessions->logout($token);
            } catch (AuthException) {
            }
        }

        return $this->success('success')->header(['Set-Cookie' => PlatformRefreshCookie::clear()]);
    }

    public function info()
    {
        if ($this->platformContext === null) {
            return $this->fail('Platform authentication is required.');
        }
        $permissions = $this->sessions->permissionKeys($this->platformContext);

        return $this->data([
            'audience' => 'platform',
            'account_id' => (string)$this->platformContext->core->accountId,
            'platform_operator_id' => (string)$this->platformContext->core->operatorId,
            'permissions' => $permissions,
            'navigation' => array_values(array_filter([
                in_array('platform.tenant.read', $permissions, true) ? '/platform/tenants' : null,
                in_array('platform.ops.read', $permissions, true) ? '/platform/ops' : null,
                in_array('platform.operator.read', $permissions, true) ? '/platform/operators' : null,
                in_array('platform.role.read', $permissions, true) ? '/platform/roles' : null,
            ])),
        ]);
    }
}
