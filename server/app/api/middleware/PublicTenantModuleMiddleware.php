<?php
declare(strict_types=1);

namespace app\api\middleware;

use app\common\execution\ConsumerExecutionContext;
use app\common\execution\ExecutionContextAccess;
use app\common\execution\ExecutionContextStore;
use app\common\http\ApiProblem;
use app\common\service\module\ModuleExecutionBoundary;
use app\common\service\tenant\DefaultTenantContextResolver;
use app\common\service\tenant\TenantEntryBindingResolver;
use PeanutAdmin\Kernel\Module\ModuleException;

/** Establishes a Host-bound public Tenant and, when present, its Module boundary. */
final class PublicTenantModuleMiddleware
{
    /** @var array<string,list<string>> */
    private const RESTRICTED_OPERATIONS = [
        'peanut.hot-search.public-read' => ['hot-search.lists'],
        'peanut.member.public-auth' => ['member.register', 'member.login'],
        'peanut.notice.verification' => ['notice.verification.send', 'notice.verification.verify'],
    ];

    public function __construct(
        private readonly ExecutionContextStore $executionContexts,
        private readonly ExecutionContextAccess $contexts,
        private readonly TenantEntryBindingResolver $entryBindings,
        private readonly ModuleExecutionBoundary $modules,
    ) {}

    public function handle(
        $request,
        \Closure $next,
        string $actor,
        string $moduleKey,
        string $operation,
    ) {
        if (isset(self::RESTRICTED_OPERATIONS[$actor])
            && !in_array($operation, self::RESTRICTED_OPERATIONS[$actor], true)) {
            throw ApiProblem::fromEnvelope('默认租户不可用', null, 50300);
        }

        try {
            $context = $this->entryBindings->system(
                $request,
                TenantEntryBindingResolver::MEMBER_CLIENT,
                $actor,
                $operation,
                DefaultTenantContextResolver::operationId($this->contexts, $request),
            );
        } catch (\Throwable) {
            throw ApiProblem::fromEnvelope(
                $moduleKey === 'official.article' ? '默认租户不可用' : '租户入口不可用',
                null,
                50300,
            );
        }

        return $this->executionContexts->run(
            ConsumerExecutionContext::publicTenant($context),
            function () use ($moduleKey, $next, $operation, $request) {
                // An empty key denotes an application-owned capability without a Module manifest.
                if ($moduleKey !== '') {
                    try {
                        $this->modules->assertHttp($moduleKey, $operation);
                    } catch (ModuleException $exception) {
                        if ($moduleKey === 'official.article') {
                            throw ApiProblem::fromEnvelope(
                                '文章模块当前不可用',
                                ['error_code' => $exception->errorCode],
                                40400,
                            );
                        }
                        throw $exception;
                    }
                }

                return $next($request);
            },
        );
    }
}
