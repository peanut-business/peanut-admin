<?php
declare(strict_types=1);

namespace app\api\middleware;

use app\common\service\JsonService;
use app\common\service\notice\NoticeTenantContext;
use app\common\service\tenant\DefaultTenantContextResolver;

/** Establishes the active default-Tenant context for anonymous verification-code operations. */
final class PublicNoticeTenantMiddleware
{
    private const OPERATIONS = ['notice.verification.send', 'notice.verification.verify'];

    public function handle($request, \Closure $next, string $operation)
    {
        if (!in_array($operation, self::OPERATIONS, true)) {
            return JsonService::fail('默认租户不可用', null, 50300);
        }
        try {
            $request->tenantContext = DefaultTenantContextResolver::system(
                NoticeTenantContext::VERIFICATION_ACTOR,
                $operation,
                DefaultTenantContextResolver::operationId($request),
            );
        } catch (\Throwable) {
            return JsonService::fail('默认租户不可用', null, 50300);
        }

        return $next($request);
    }
}
