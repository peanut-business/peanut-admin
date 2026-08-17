<?php
declare(strict_types=1);

namespace app\platform\http\middleware;

use app\common\service\JsonService;
use app\common\service\tenant\ApplicationHostPolicy;
use app\platform\http\PlatformRequest;
use app\platform\service\PlatformRuntimeFactory;
use PeanutAdmin\Kernel\Auth\AuthException;

final class PlatformLoginMiddleware
{
    public function handle($request, \Closure $next)
    {
        try {
            ApplicationHostPolicy::production()->assertPlatform($request);
        } catch (\DomainException|\InvalidArgumentException) {
            return JsonService::fail('Platform host is not allowed.', null, 40300);
        }
        $token = PlatformRequest::bearerToken($request);
        if ($token === '') {
            return JsonService::fail('Platform authentication is required.', null, 40100);
        }

        try {
            $request->platformContext = PlatformRuntimeFactory::sessions()->context(
                $token,
                PlatformRequest::requestId($request)
            );
        } catch (AuthException|\DomainException|\InvalidArgumentException) {
            return JsonService::fail('Platform authentication credential is invalid.', null, 40100);
        }

        return $next($request);
    }
}
