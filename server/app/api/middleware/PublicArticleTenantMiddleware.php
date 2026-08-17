<?php
declare(strict_types=1);

namespace app\api\middleware;

use app\common\service\article\ArticleTenantContext;
use app\common\service\JsonService;
use app\common\service\tenant\DefaultTenantContextResolver;

/** Establishes the trusted default-tenant context for anonymous article reads. */
final class PublicArticleTenantMiddleware
{
    public function handle($request, \Closure $next, string $operation)
    {
        try {
            $request->tenantContext = DefaultTenantContextResolver::system(
                ArticleTenantContext::PUBLIC_ACTOR,
                $operation,
                DefaultTenantContextResolver::operationId($request),
            );
        } catch (\Throwable) {
            return JsonService::fail('默认租户不可用', null, 50300);
        }

        return $next($request);
    }
}
