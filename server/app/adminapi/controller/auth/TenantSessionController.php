<?php
declare(strict_types=1);

namespace app\adminapi\controller\auth;

use app\common\controller\BaseLikeAdminController;
use app\common\service\tenant\ApplicationHostPolicy;
use app\common\service\tenant\TenantEntryBindingResolver;
use app\platform\http\PlatformRequest;
use PeanutAdmin\Kernel\Auth\AuthException;
use PeanutAdmin\Kernel\Http\TenantAuthEndpoint;
use PeanutAdmin\Kernel\Http\TenantAuthResponse;
use think\App;

final class TenantSessionController extends BaseLikeAdminController
{
    public function __construct(
        App $app,
        private readonly TenantAuthEndpoint $tenantAuth,
        private readonly ApplicationHostPolicy $hostPolicy,
        private readonly TenantEntryBindingResolver $entryBindings,
    ) {
        parent::__construct($app);
    }

    public function login()
    {
        $params = $this->request->post();
        try {
            $this->hostPolicy->assertTenantAdmin($this->request);
            $tenantCode = $this->entryBindings->loginTenantCode(
                $this->request,
                TenantEntryBindingResolver::ADMIN_CLIENT,
                isset($params['tenant_code']) ? (string)$params['tenant_code'] : null,
            );
            return $this->response($this->tenantAuth->login(
                trim((string)($params['email'] ?? '')),
                (string)($params['password'] ?? ''),
                $tenantCode,
                $this->request->ip(),
                $this->request->header('User-Agent'),
                PlatformRequest::requestId($this->request)
            ));
        } catch (AuthException|\DomainException|\InvalidArgumentException) {
            throw \app\common\http\ApiProblem::fromEnvelope('Tenant authentication was rejected.', null, 40100);
        }
    }

    public function select()
    {
        $params = $this->request->post();
        try {
            $this->hostPolicy->assertTenantAdmin($this->request);
            $this->entryBindings->assertTenantAccess(
                $this->request,
                TenantEntryBindingResolver::ADMIN_CLIENT,
                (int)($params['tenant_id'] ?? 0),
            );
            return $this->response($this->tenantAuth->selectTenant(
                trim((string)($params['challenge_token'] ?? '')),
                (int)($params['tenant_id'] ?? 0),
                $this->request->ip(),
                $this->request->header('User-Agent'),
                PlatformRequest::requestId($this->request)
            ));
        } catch (AuthException|\DomainException|\InvalidArgumentException) {
            throw \app\common\http\ApiProblem::fromEnvelope('Tenant selection was rejected.', null, 40300);
        }
    }

    public function switchChallenge()
    {
        try {
            $this->hostPolicy->assertTenantAdmin($this->request);
            if ($this->entryBindings->boundTenantId(
                $this->request,
                TenantEntryBindingResolver::ADMIN_CLIENT,
            ) !== null) {
                throw new \DomainException('TENANT_SWITCH_BOUND_ENTRY');
            }
            return $this->response($this->tenantAuth->switchChallenge(
                PlatformRequest::bearerToken($this->request),
                $this->request->ip(),
                $this->request->header('User-Agent'),
                PlatformRequest::requestId($this->request)
            ));
        } catch (AuthException|\DomainException|\InvalidArgumentException) {
            throw \app\common\http\ApiProblem::fromEnvelope('Tenant switch was rejected.', null, 40300);
        }
    }

    public function refresh()
    {
        try {
            $this->hostPolicy->assertTenantAdmin($this->request);
            return $this->response($this->tenantAuth->refresh(
                trim((string)$this->request->cookie($this->tenantAuth->refreshCookieName(), '')),
                $this->isTrustedBrowserOrigin(),
                $this->request->ip(),
                $this->request->header('User-Agent'),
                PlatformRequest::requestId($this->request)
            ));
        } catch (AuthException|\DomainException|\InvalidArgumentException) {
            throw \app\common\http\ApiProblem::fromEnvelope('Tenant refresh credential is invalid.', null, 40100);
        }
    }

    public function logout()
    {
        try {
            $this->hostPolicy->assertTenantAdmin($this->request);
            return $this->response($this->tenantAuth->logout(
                PlatformRequest::bearerToken($this->request),
                PlatformRequest::requestId($this->request)
            ));
        } catch (AuthException|\DomainException|\InvalidArgumentException) {
            throw \app\common\http\ApiProblem::fromEnvelope('Tenant session is invalid.', null, 40100);
        }
    }

    private function response(TenantAuthResponse $result)
    {
        $response = json($result->body ?? ['code' => 20000, 'msg' => 'success', 'data' => null], $result->status);
        return $result->headers === [] ? $response : $response->header($result->headers);
    }

    private function isTrustedBrowserOrigin(): bool
    {
        if (strtolower(trim((string)$this->request->header('Sec-Fetch-Site', ''))) !== 'same-origin') {
            return false;
        }
        $origin = trim((string)$this->request->header('Origin', ''));
        $originHost = parse_url($origin, PHP_URL_HOST);
        if (!is_string($originHost) || $originHost === '') {
            return false;
        }
        return hash_equals(
            TenantEntryBindingResolver::normalizeHost((string)$this->request->host()),
            TenantEntryBindingResolver::normalizeHost($originHost),
        );
    }
}
