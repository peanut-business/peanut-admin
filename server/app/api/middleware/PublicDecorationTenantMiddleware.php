<?php
declare(strict_types=1);

namespace app\api\middleware;

use app\common\service\decoration\DecorationTenantContext;
use app\common\service\JsonService;
use app\common\service\tenant\DefaultTenantContextResolver;

/** Establishes the trusted default-tenant context for anonymous decoration reads. */
final class PublicDecorationTenantMiddleware
{
    public function handle($request, \Closure $next, string $operation)
    {
        try {
            $request->tenantContext = DefaultTenantContextResolver::system(
                DecorationTenantContext::PUBLIC_ACTOR,
                $operation,
                DefaultTenantContextResolver::operationId($request),
            );
        } catch (\Throwable) {
            return JsonService::fail('默认租户不可用', null, 50300);
        }

        return $next($request);
    }
}
