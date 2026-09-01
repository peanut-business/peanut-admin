<?php
declare(strict_types=1);

namespace app\api\middleware;

use app\common\execution\ExecutionContextStore;
use app\common\service\decoration\DecorationTenantContext;
use app\common\service\JsonService;
use app\common\service\tenant\DefaultTenantContextResolver;
use app\common\service\tenant\TenantEntryBindingResolver;

/** Establishes the Host-bound Tenant context for anonymous decoration reads. */
final class PublicDecorationTenantMiddleware
{
    public function __construct(private readonly ?ExecutionContextStore $executionContexts = null)
    {
    }

    public function handle($request, \Closure $next, string $operation)
    {
        try {
            $context = TenantEntryBindingResolver::production()->system(
                $request,
                TenantEntryBindingResolver::MEMBER_CLIENT,
                DecorationTenantContext::PUBLIC_ACTOR,
                $operation,
                DefaultTenantContextResolver::operationId($request),
            );
        } catch (\Throwable) {
            throw \app\common\http\ApiProblem::fromEnvelope('租户入口不可用', null, 50300);
        }

        return ($this->executionContexts ?? app(ExecutionContextStore::class))->run(
            \app\common\execution\ConsumerExecutionContext::publicTenant($context),
            static fn() => $next($request),
        );
    }
}
