<?php
declare(strict_types=1);

namespace app\api\middleware;

use app\common\service\JsonService;
use app\common\service\member\MemberTenantContext;
use app\common\service\tenant\DefaultTenantContextResolver;

/** Establishes the active default-Tenant context for anonymous member authentication. */
final class PublicMemberTenantMiddleware
{
    private const OPERATIONS = ['member.register', 'member.login'];

    public function handle($request, \Closure $next, string $operation)
    {
        if (!in_array($operation, self::OPERATIONS, true)) {
            return JsonService::fail('默认租户不可用', null, 50300);
        }
        try {
            $request->tenantContext = DefaultTenantContextResolver::system(
                MemberTenantContext::PUBLIC_AUTH_ACTOR,
                $operation,
                DefaultTenantContextResolver::operationId($request),
            );
        } catch (\Throwable) {
            return JsonService::fail('默认租户不可用', null, 50300);
        }

        return $next($request);
    }
}
