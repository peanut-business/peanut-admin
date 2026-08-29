<?php
declare(strict_types=1);

namespace app\adminapi\http\middleware;

use app\adminapi\http\AdminRequest;
use app\adminapi\service\AdminTokenService;
use app\common\execution\ExecutionContext;
use app\common\execution\ExecutionContextStore;
use app\common\service\authorization\AdminAuthorizationService;
use app\common\service\JsonService;
use app\common\service\tenant\TenantEntryBindingResolver;
use app\common\service\tenant\ApplicationHostPolicy;
use app\tenant\service\TenantAuthRuntimeFactory;

/** Establishes management identity only from a validated native Tenant session. */
final class LoginMiddleware
{
    public function __construct(private readonly ?ExecutionContextStore $executionContexts = null)
    {
    }

    public function handle($request, \Closure $next)
    {
        $token = AdminTokenService::tokenFromRequest($request);
        if ($token === '') {
            throw \app\common\http\ApiProblem::fromEnvelope('请求缺少 token', null, 40100);
        }
        if (!str_starts_with($token, 'pa_tat_')) {
            throw \app\common\http\ApiProblem::fromEnvelope('登录超时，请重新登录', null, 40100);
        }

        try {
            ApplicationHostPolicy::production()->assertTenantAdmin($request);
            $context = TenantAuthRuntimeFactory::service()->context(
                $token,
                AdminRequest::requestId($request),
            );
            $entryBindings = TenantEntryBindingResolver::production();
            $entryBindings->assertTenantAccess(
                $request,
                TenantEntryBindingResolver::ADMIN_CLIENT,
                $context->tenantId,
            );
            $principal = (new AdminAuthorizationService())->principal($context)->toArray();
            $principal['terminal'] = 1;
            $entryBound = $entryBindings->boundTenantId(
                $request,
                TenantEntryBindingResolver::ADMIN_CLIENT,
            ) !== null;
        } catch (\Throwable) {
            throw \app\common\http\ApiProblem::fromEnvelope('租户会话不可用', null, 40300);
        }

        $operation = sprintf(
            'http.admin.%s.%s',
            strtolower((string)$request->method()),
            trim((string)$request->pathinfo(), '/'),
        );
        return ($this->executionContexts ?? app(ExecutionContextStore::class))->run(
            ExecutionContext::tenantAdmin($context, $operation, $principal, $entryBound),
            static fn() => $next($request),
        );
    }
}
