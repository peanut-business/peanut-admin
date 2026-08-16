<?php
declare(strict_types=1);

namespace app\tenant\controller;

use app\common\controller\BaseLikeAdminController;
use app\common\service\JsonService;
use app\common\service\tenant\TenantEntryBindingResolver;
use app\common\service\tenant\ApplicationHostPolicy;
use app\platform\http\PlatformRequest;
use app\tenant\service\TenantAuthRuntimeFactory;
use PeanutAdmin\Kernel\Auth\AuthException;
use PeanutAdmin\Kernel\Http\TenantAuthResponse;

final class TenantSessionController extends BaseLikeAdminController
{
    public function login()
    {
        $params = $this->request->post();
        try {
            ApplicationHostPolicy::production()->assertTenantAdmin($this->request);
            $tenantCode = TenantEntryBindingResolver::production()->loginTenantCode(
                $this->request,
                TenantEntryBindingResolver::ADMIN_CLIENT,
                isset($params['tenant_code']) ? (string)$params['tenant_code'] : null,
            );
            return $this->response(TenantAuthRuntimeFactory::endpoint()->login(
                trim((string)($params['email'] ?? '')),
                (string)($params['password'] ?? ''),
                $tenantCode,
                $this->request->ip(),
                $this->request->header('User-Agent'),
                PlatformRequest::requestId($this->request)
            ));
        } catch (AuthException|\DomainException|\InvalidArgumentException) {
            return JsonService::fail('Tenant authentication was rejected.', null, 40100);
        }
    }

    public function select()
    {
        $params = $this->request->post();
        try {
            ApplicationHostPolicy::production()->assertTenantAdmin($this->request);
            TenantEntryBindingResolver::production()->assertTenantAccess(
                $this->request,
                TenantEntryBindingResolver::ADMIN_CLIENT,
                (int)($params['tenant_id'] ?? 0),
            );
            return $this->response(TenantAuthRuntimeFactory::endpoint()->selectTenant(
                trim((string)($params['challenge_token'] ?? '')),
                (int)($params['tenant_id'] ?? 0),
                $this->request->ip(),
                $this->request->header('User-Agent'),
                PlatformRequest::requestId($this->request)
            ));
        } catch (AuthException|\DomainException|\InvalidArgumentException) {
            return JsonService::fail('Tenant selection was rejected.', null, 40300);
        }
    }

    public function switchChallenge()
    {
        try {
            ApplicationHostPolicy::production()->assertTenantAdmin($this->request);
            if (TenantEntryBindingResolver::production()->boundTenantId(
                $this->request,
                TenantEntryBindingResolver::ADMIN_CLIENT,
            ) !== null) {
                throw new \DomainException('TENANT_SWITCH_BOUND_ENTRY');
            }
            return $this->response(TenantAuthRuntimeFactory::endpoint()->switchChallenge(
                PlatformRequest::bearerToken($this->request),
                $this->request->ip(),
                $this->request->header('User-Agent'),
                PlatformRequest::requestId($this->request)
            ));
        } catch (AuthException|\DomainException|\InvalidArgumentException) {
            return JsonService::fail('Tenant switch was rejected.', null, 40300);
        }
    }

    public function refresh()
    {
        try {
            ApplicationHostPolicy::production()->assertTenantAdmin($this->request);
            $endpoint = TenantAuthRuntimeFactory::endpoint();
            return $this->response($endpoint->refresh(
                trim((string)$this->request->cookie($endpoint->refreshCookieName(), '')),
                $this->isTrustedBrowserOrigin(),
                $this->request->ip(),
                $this->request->header('User-Agent'),
                PlatformRequest::requestId($this->request)
            ));
        } catch (AuthException|\DomainException|\InvalidArgumentException) {
            return JsonService::fail('Tenant refresh credential is invalid.', null, 40100);
        }
    }

    public function logout()
    {
        try {
            ApplicationHostPolicy::production()->assertTenantAdmin($this->request);
            return $this->response(TenantAuthRuntimeFactory::endpoint()->logout(
                PlatformRequest::bearerToken($this->request),
                PlatformRequest::requestId($this->request)
            ));
        } catch (AuthException|\DomainException|\InvalidArgumentException) {
            return JsonService::fail('Tenant session is invalid.', null, 40100);
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
