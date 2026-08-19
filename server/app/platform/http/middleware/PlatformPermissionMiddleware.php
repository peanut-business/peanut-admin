<?php
declare(strict_types=1);

namespace app\platform\http\middleware;

use app\common\service\JsonService;
use app\common\service\DemoAccountPolicy;
use app\platform\context\PlatformOperatorContext;
use app\platform\service\PlatformRuntimeFactory;
use PeanutAdmin\Kernel\Authorization\AuthorizationException;
use think\facade\Db;

final class PlatformPermissionMiddleware
{
    public function handle($request, \Closure $next, string $permission)
    {
        $context = $request->platformContext ?? null;
        if (!$context instanceof PlatformOperatorContext) {
            return JsonService::fail('Platform authentication is required.', null, 40100);
        }
        if (!str_starts_with($permission, 'platform.')) {
            return JsonService::fail('Platform permission boundary is invalid.', null, 40300);
        }

        try {
            PlatformRuntimeFactory::sessions()->assertAllowed($context, $permission);
        } catch (AuthorizationException) {
            return JsonService::fail('Platform permission is required.', null, 40300);
        }

        if (in_array(strtoupper((string)$request->method()), ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
            $pdo = Db::connect()->connect();
            if ($pdo instanceof \PDO
                && DemoAccountPolicy::platformMutationLocked($pdo, $context->core->accountId)) {
                return JsonService::fail('演示账号已锁定平台权限和关键配置操作', null, 40300);
            }
        }

        return $next($request);
    }
}
