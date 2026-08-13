<?php
declare(strict_types=1);

namespace app\api\middleware;

use app\common\service\decoration\DecorationTenantContext;
use app\common\service\JsonService;
use PeanutAdmin\Kernel\Context\TenantSystemContext;
use think\facade\Db;

/** Establishes the trusted default-tenant context for anonymous decoration reads. */
final class PublicDecorationTenantMiddleware
{
    public function handle($request, \Closure $next, string $operation)
    {
        $tenantId = (int) Db::name('default_tenant_bootstrap')
            ->where('status', 'completed')
            ->value('tenant_id');
        if ($tenantId < 1 || $operation === '') {
            return JsonService::fail('默认租户不可用', null, 50300);
        }

        $operationId = trim((string) $request->header('X-Request-Id', ''));
        if ($operationId === '') {
            $operationId = bin2hex(random_bytes(16));
        }
        $request->tenantContext = new TenantSystemContext(
            $tenantId,
            DecorationTenantContext::PUBLIC_ACTOR,
            $operation,
            $operationId,
        );

        return $next($request);
    }
}
