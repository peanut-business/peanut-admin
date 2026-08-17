<?php
declare(strict_types=1);

namespace app\api\middleware;

use app\common\service\JsonService;
use app\common\service\member\MemberTenantContext;
use app\common\service\tenant\DefaultTenantContextResolver;
use app\common\service\tenant\TenantEntryBindingResolver;

/** Establishes a bound Tenant, or the unique active default when no binding exists. */
final class PublicMemberTenantMiddleware
{
    private const OPERATIONS = ['member.register', 'member.login'];

    public function handle($request, \Closure $next, string $operation)
    {
        if (!in_array($operation, self::OPERATIONS, true)) {
            return JsonService::fail('默认租户不可用', null, 50300);
        }
        try {
            $request->tenantContext = TenantEntryBindingResolver::production()->system(
                $request,
                TenantEntryBindingResolver::MEMBER_CLIENT,
                MemberTenantContext::PUBLIC_AUTH_ACTOR,
                $operation,
                DefaultTenantContextResolver::operationId($request),
            );
        } catch (\Throwable) {
            return JsonService::fail('租户入口不可用', null, 50300);
        }

        return $next($request);
    }
}
