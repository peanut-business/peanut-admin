<?php
declare(strict_types=1);

namespace app\adminapi\http\middleware;

use app\adminapi\service\AdminApiAccessRegistry;
use app\common\dto\authorization\AdminPrincipal;
use app\common\dto\authorization\PermissionDecision;
use app\common\service\authorization\AdminAuthorizationService;
use app\common\service\JsonService;
use app\common\service\DemoAccountPolicy;
use app\common\execution\ExecutionContext;
use app\common\execution\ExecutionContextAccess;

/**
 * 权限中间件（原生 TP 风格）
 *
 * 必须在 LoginMiddleware 之后执行（依赖 $request->adminInfo）。
 * 只有版本化 authenticated 元数据或启用的精确权限节点可以放行。
 * root 只绕过角色授权，不能绕过路由登记、登录、TenantContext 或身份边界。
 */
class AuthMiddleware
{
    public function handle($request, \Closure $next)
    {
        $current = ExecutionContextAccess::current();
        $adminInfo = $current?->actorType === ExecutionContext::TENANT_ADMIN
            ? ExecutionContextAccess::principal()
            : null;
        if (empty($adminInfo)) {
            throw \app\common\http\ApiProblem::fromEnvelope('请先登录', null, 40100);
        }

        $path = 'adminapi/' . strtolower(trim($request->pathinfo(), '/'));
        if (AdminApiAccessRegistry::isAuthenticatedOnly((string)$request->method(), $path)) {
            return $next($request);
        }

        // 权限字符使用 adminapi/ 之后的精确路径，不做 URI alias 展开。
        $accessUri = substr($path, strlen('adminapi/'));

        $tenantContext = $current?->scope;
        $decision = $tenantContext instanceof \PeanutAdmin\Kernel\Auth\TenantContext
            ? (new AdminAuthorizationService())->decide(
                $tenantContext,
                AdminPrincipal::fromArray($adminInfo),
                $accessUri,
            )
            : PermissionDecision::deny($accessUri, 'INVALID_TENANT_ADMIN_CONTEXT');
        if (!$decision->allowed) {
            throw \app\common\http\ApiProblem::fromEnvelope('暂无访问权限', null, 40300);
        }

        if (in_array(strtoupper((string)$request->method()), ['POST', 'PUT', 'PATCH', 'DELETE'], true)
            && DemoAccountPolicy::mutationLocked($adminInfo, $accessUri)) {
            throw \app\common\http\ApiProblem::fromEnvelope('演示账号已锁定关键配置和权限操作', null, 40300);
        }

        return $next($request);
    }
}
