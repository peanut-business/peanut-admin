<?php
declare(strict_types=1);

namespace app\platform\http\middleware;

use app\common\service\JsonService;
use app\platform\context\PlatformOperatorContext;
use app\platform\service\PlatformRuntimeFactory;
use PeanutAdmin\Kernel\Authorization\AuthorizationException;

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

        return $next($request);
    }
}
