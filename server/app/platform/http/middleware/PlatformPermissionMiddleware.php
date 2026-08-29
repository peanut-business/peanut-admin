<?php
declare(strict_types=1);

namespace app\platform\http\middleware;

use app\common\service\JsonService;
use app\common\service\DemoAccountPolicy;
use app\platform\context\PlatformOperatorContext;
use app\platform\service\PlatformRuntimeFactory;
use PeanutAdmin\Kernel\Authorization\AuthorizationException;
use think\facade\Db;
use app\common\execution\ExecutionContextAccess;

final class PlatformPermissionMiddleware
{
    public function handle($request, \Closure $next, string $permission)
    {
        try {
            $context = ExecutionContextAccess::platform();
        } catch (\Throwable) {
            $context = null;
        }
        if (!$context instanceof PlatformOperatorContext) {
            throw \app\common\http\ApiProblem::fromEnvelope('Platform authentication is required.', null, 40100);
        }
        if (!str_starts_with($permission, 'platform.')) {
            throw \app\common\http\ApiProblem::fromEnvelope('Platform permission boundary is invalid.', null, 40300);
        }

        try {
            PlatformRuntimeFactory::sessions()->assertAllowed($context, $permission);
        } catch (AuthorizationException) {
            throw \app\common\http\ApiProblem::fromEnvelope('Platform permission is required.', null, 40300);
        }

        if (in_array(strtoupper((string)$request->method()), ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
            $pdo = Db::connect()->connect();
            if ($pdo instanceof \PDO
                && DemoAccountPolicy::platformMutationLocked($pdo, $context->core->accountId)) {
                throw \app\common\http\ApiProblem::fromEnvelope('演示账号已锁定平台权限和关键配置操作', null, 40300);
            }
        }

        return $next($request);
    }
}
