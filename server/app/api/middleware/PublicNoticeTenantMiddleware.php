<?php
declare(strict_types=1);

namespace app\api\middleware;

use app\common\execution\ExecutionContextStore;
use app\common\service\JsonService;
use app\common\service\notice\NoticeTenantContext;
use app\common\service\tenant\DefaultTenantContextResolver;
use app\common\service\tenant\TenantEntryBindingResolver;

/** Establishes the Host-bound Tenant context for anonymous verification-code operations. */
final class PublicNoticeTenantMiddleware
{
    private const OPERATIONS = ['notice.verification.send', 'notice.verification.verify'];

    public function __construct(private readonly ?ExecutionContextStore $executionContexts = null)
    {
    }

    public function handle($request, \Closure $next, string $operation)
    {
        if (!in_array($operation, self::OPERATIONS, true)) {
            throw \app\common\http\ApiProblem::fromEnvelope('默认租户不可用', null, 50300);
        }
        try {
            $context = TenantEntryBindingResolver::production()->system(
                $request,
                TenantEntryBindingResolver::MEMBER_CLIENT,
                NoticeTenantContext::VERIFICATION_ACTOR,
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
