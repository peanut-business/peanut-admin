<?php
declare(strict_types=1);

namespace app\platform\controller;

use app\platform\http\PlatformRequest;
use app\platform\service\PlatformRuntimeFactory;
use app\platform\validate\PlatformLoginValidate;
use PeanutAdmin\Kernel\Auth\AuthException;
use PeanutAdmin\Kernel\Auth\PlatformRefreshCookie;

final class PlatformSessionController extends BasePlatformController
{
    public function login()
    {
        $params = $this->request->post();
        $this->validate($params, PlatformLoginValidate::class);
        try {
            $authentication = PlatformRuntimeFactory::sessions()->login(
                trim((string)$params['email']),
                (string)$params['password'],
                $this->request->ip(),
                $this->request->header('User-Agent'),
                PlatformRequest::requestId($this->request)
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
            $authentication = PlatformRuntimeFactory::sessions()->refresh(
                $token,
                $this->request->ip(),
                $this->request->header('User-Agent'),
                PlatformRequest::requestId($this->request)
            );
        } catch (AuthException|\DomainException|\InvalidArgumentException) {
            return $this->fail('Platform refresh credential is invalid.')
                ->header(['Set-Cookie' => PlatformRefreshCookie::clear()]);
        }

        return $this->data($authentication->responseData())
            ->header(['Set-Cookie' => PlatformRefreshCookie::issue($authentication->tokens->refresh)]);
    }

    public function logout()
    {
        $token = PlatformRequest::bearerToken($this->request);
        if ($token !== '') {
            try {
                PlatformRuntimeFactory::sessions()->logout($token);
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
        $permissions = PlatformRuntimeFactory::sessions()->permissionKeys($this->platformContext);

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
