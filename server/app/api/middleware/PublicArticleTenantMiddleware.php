<?php
declare(strict_types=1);

namespace app\api\middleware;

use app\common\service\article\ArticleTenantContext;
use app\common\service\module\ModuleExecutionContext;
use app\common\service\JsonService;
use app\platform\service\module\PdoModuleGovernanceProvider;
use app\common\service\tenant\DefaultTenantContextResolver;
use app\common\service\tenant\TenantEntryBindingResolver;
use PeanutAdmin\Kernel\Module\ModuleException;
use think\facade\Db;

/** Establishes the trusted default-tenant context for anonymous article reads. */
final class PublicArticleTenantMiddleware
{
    public function handle($request, \Closure $next, string $operation)
    {
        try {
            $request->tenantContext = TenantEntryBindingResolver::production()->system(
                $request,
                TenantEntryBindingResolver::MEMBER_CLIENT,
                ArticleTenantContext::PUBLIC_ACTOR,
                $operation,
                DefaultTenantContextResolver::operationId($request),
            );
            $pdo = Db::connect()->connect();
            if (!$pdo instanceof \PDO) {
                throw new \RuntimeException('ARTICLE_MODULE_DATABASE_UNAVAILABLE');
            }
            PdoModuleGovernanceProvider::forExecution($pdo)
                ->executionGuard('official.article')
                ->assertEnabled(
                ModuleExecutionContext::system('official.article', $request->tenantContext),
            );
        } catch (ModuleException $exception) {
            return JsonService::fail('文章模块当前不可用', ['error_code' => $exception->errorCode], 40400);
        } catch (\Throwable) {
            return JsonService::fail('默认租户不可用', null, 50300);
        }

        return $next($request);
    }
}
