<?php
declare(strict_types=1);

namespace app\common\service\module;

use app\common\service\JsonService;
use app\common\service\member\AuthenticatedMemberContext;
use app\platform\service\module\PdoModuleGovernanceProvider;
use PDO;
use PeanutAdmin\Kernel\Auth\TenantContext;
use PeanutAdmin\Kernel\Context\TenantSystemContext;
use PeanutAdmin\Kernel\Module\ModuleException;
use think\facade\Db;

/** Enforces deployment and Tenant enablement after an upstream identity boundary. */
final class OfficialModuleMiddleware
{
    public function handle($request, \Closure $next, string $moduleKey, string $operation)
    {
        try {
            $pdo = Db::connect()->connect();
            if (!$pdo instanceof PDO) {
                throw new \RuntimeException('MODULE_DATABASE_UNAVAILABLE');
            }
            $tenantContext = $request->tenantContext ?? null;
            $memberContext = $request->authenticatedMemberContext ?? null;
            $context = match (true) {
                $tenantContext instanceof TenantContext => ModuleExecutionContext::admin(
                    $moduleKey,
                    $tenantContext,
                    $operation,
                ),
                $tenantContext instanceof TenantSystemContext => ModuleExecutionContext::system(
                    $moduleKey,
                    $tenantContext,
                ),
                $memberContext instanceof AuthenticatedMemberContext => ModuleExecutionContext::businessMember(
                    $moduleKey,
                    $memberContext,
                    $operation,
                ),
                default => throw new \RuntimeException('MODULE_TENANT_CONTEXT_UNAVAILABLE'),
            };
            PdoModuleGovernanceProvider::forExecution($pdo)
                ->executionGuard($moduleKey)
                ->assertEnabled($context);
        } catch (ModuleException $exception) {
            $status = in_array($exception->errorCode, ['MODULE_NOT_INSTALLED', 'MODULE_INSTALLATION_FAILED'], true)
                ? 50300
                : 40300;
            return JsonService::fail('模块当前不可用', ['error_code' => $exception->errorCode], $status);
        } catch (\Throwable) {
            return JsonService::fail('模块当前不可用', null, 50300);
        }

        return $next($request);
    }
}
