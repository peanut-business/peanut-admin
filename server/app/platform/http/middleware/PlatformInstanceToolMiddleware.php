<?php
declare(strict_types=1);

namespace app\platform\http\middleware;

use app\common\service\instance\InstanceToolAccessGuard;
use app\common\service\JsonService;
use think\facade\Config;

/** Environment/deployment gate applied after Platform authentication and exact permission checks. */
final class PlatformInstanceToolMiddleware
{
    public function handle($request, \Closure $next)
    {
        if (strtolower(trim((string)env('APP_ENV', ''))) !== 'development'
            || !app()->isDebug()
            || !InstanceToolAccessGuard::fromConfiguredValue(Config::get('deployment.mode'))->allows()) {
            return JsonService::fail(
                'Runtime Module mutation is disabled.',
                ['error_code' => 'MODULE_RUNTIME_MUTATION_DISABLED'],
                40300,
            );
        }
        return $next($request);
    }
}
