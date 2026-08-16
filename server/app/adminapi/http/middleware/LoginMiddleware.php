<?php
declare(strict_types=1);

namespace app\adminapi\http\middleware;

use app\adminapi\http\AdminRequest;
use app\adminapi\service\AdminTokenService;
use app\adminapi\service\NativeAdminPrincipalRepository;
use app\common\service\JsonService;
use app\common\service\tenant\TenantEntryBindingResolver;
use app\tenant\service\TenantAuthRuntimeFactory;

/** Establishes management identity only from a validated native Tenant session. */
final class LoginMiddleware
{
    public function handle($request, \Closure $next)
    {
        $token = AdminTokenService::tokenFromRequest($request);
        if ($token === '') {
            return JsonService::fail('请求缺少 token', null, 40100);
        }
        if (!str_starts_with($token, 'pa_tat_')) {
            return JsonService::fail('登录超时，请重新登录', null, 40100);
        }

        try {
            $context = TenantAuthRuntimeFactory::service()->context(
                $token,
                AdminRequest::requestId($request),
            );
            $entryBindings = TenantEntryBindingResolver::production();
            $entryBindings->assertTenantAccess(
                $request,
                TenantEntryBindingResolver::ADMIN_CLIENT,
                $context->tenantId,
            );
            $principal = (new NativeAdminPrincipalRepository())->require($context);
            $principal['token'] = $token;
            $principal['terminal'] = 1;
            $request->adminInfo = $principal;
            $request->adminId = (int)$principal['id'];
            $request->tenantContext = $context;
            $request->tenantEntryBound = $entryBindings->boundTenantId(
                $request,
                TenantEntryBindingResolver::ADMIN_CLIENT,
            ) !== null;
        } catch (\Throwable) {
            return JsonService::fail('租户会话不可用', null, 40300);
        }

        return $next($request);
    }
}
