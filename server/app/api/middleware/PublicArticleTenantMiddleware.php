<?php
declare(strict_types=1);

namespace app\api\middleware;

use app\common\execution\ExecutionContextStore;
use app\common\service\article\ArticleTenantContext;
use app\common\service\module\ModuleExecutionBoundary;
use app\common\service\JsonService;
use app\common\service\tenant\DefaultTenantContextResolver;
use app\common\service\tenant\TenantEntryBindingResolver;
use PeanutAdmin\Kernel\Module\ModuleException;

/** Establishes the trusted default-tenant context for anonymous article reads. */
final class PublicArticleTenantMiddleware
{
    public function __construct(
        private readonly ExecutionContextStore $executionContexts,
        private readonly ModuleExecutionBoundary $modules,
    ) {}

    public function handle($request, \Closure $next, string $operation)
    {
        try {
            $context = TenantEntryBindingResolver::production()->system(
                $request,
                TenantEntryBindingResolver::MEMBER_CLIENT,
                ArticleTenantContext::PUBLIC_ACTOR,
                $operation,
                DefaultTenantContextResolver::operationId($request),
            );
        } catch (\Throwable) {
            throw \app\common\http\ApiProblem::fromEnvelope('默认租户不可用', null, 50300);
        }

        $modules = $this->modules;
        return $this->executionContexts->run(
            \app\common\execution\ConsumerExecutionContext::publicTenant($context),
            static function () use ($modules, $next, $request) {
                try {
                    $modules->assertHttp('official.article');
                } catch (ModuleException $exception) {
                    throw \app\common\http\ApiProblem::fromEnvelope('文章模块当前不可用', ['error_code' => $exception->errorCode], 40400);
                } catch (\Throwable) {
                    throw \app\common\http\ApiProblem::fromEnvelope('默认租户不可用', null, 50300);
                }

                return $next($request);
            },
        );
    }
}
