<?php
declare(strict_types=1);

namespace app\api\middleware;

use app\common\service\decoration\DecorationTenantContext;
use app\common\service\JsonService;
use app\common\service\tenant\DefaultTenantContextResolver;
use app\common\service\tenant\TenantEntryBindingResolver;

/** Establishes the Host-bound Tenant context for anonymous decoration reads. */
final class PublicDecorationTenantMiddleware
{
    public function handle($request, \Closure $next, string $operation)
    {
        try {
            $request->tenantContext = TenantEntryBindingResolver::production()->system(
                $request,
                TenantEntryBindingResolver::MEMBER_CLIENT,
                DecorationTenantContext::PUBLIC_ACTOR,
                $operation,
                DefaultTenantContextResolver::operationId($request),
            );
        } catch (\Throwable) {
            return JsonService::fail('租户入口不可用', null, 50300);
        }

        return $next($request);
    }
}
