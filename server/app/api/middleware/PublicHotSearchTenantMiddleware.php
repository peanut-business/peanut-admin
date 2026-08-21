<?php
declare(strict_types=1);

namespace app\api\middleware;

use app\common\service\hot_search\HotSearchTenantContext;
use app\common\service\JsonService;
use app\common\service\tenant\DefaultTenantContextResolver;
use app\common\service\tenant\TenantEntryBindingResolver;

/** Establishes the Host-bound Tenant context for anonymous hot-search reads. */
final class PublicHotSearchTenantMiddleware
{
    public function handle($request, \Closure $next)
    {
        try {
            $request->tenantContext = TenantEntryBindingResolver::production()->system(
                $request,
                TenantEntryBindingResolver::MEMBER_CLIENT,
                HotSearchTenantContext::PUBLIC_ACTOR,
                HotSearchTenantContext::PUBLIC_LIST_OPERATION,
                DefaultTenantContextResolver::operationId($request),
            );
        } catch (\Throwable) {
            return JsonService::fail('租户入口不可用', null, 50300);
        }

        return $next($request);
    }
}
