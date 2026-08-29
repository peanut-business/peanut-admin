<?php
declare(strict_types=1);

namespace app\platform\http\middleware;

use app\common\service\JsonService;
use app\common\service\tenant\ApplicationHostPolicy;

final class PlatformHostMiddleware
{
    public function handle($request, \Closure $next)
    {
        try {
            ApplicationHostPolicy::production()->assertPlatform($request);
        } catch (\DomainException|\InvalidArgumentException) {
            throw \app\common\http\ApiProblem::fromEnvelope('Platform host is not allowed.', null, 40300);
        }

        return $next($request);
    }
}
