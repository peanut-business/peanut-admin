<?php
declare(strict_types=1);

namespace app\platform\http\middleware;

use app\common\execution\ExecutionContextStore;
use app\common\service\JsonService;
use app\common\service\tenant\ApplicationHostPolicy;
use app\platform\http\PlatformRequest;
use app\platform\service\PlatformRuntimeFactory;
use PeanutAdmin\Kernel\Auth\AuthException;

final class PlatformLoginMiddleware
{
    public function __construct(private readonly ?ExecutionContextStore $executionContexts = null)
    {
    }

    public function handle($request, \Closure $next)
    {
        try {
            ApplicationHostPolicy::production()->assertPlatform($request);
        } catch (\DomainException|\InvalidArgumentException) {
            throw \app\common\http\ApiProblem::fromEnvelope('Platform host is not allowed.', null, 40300);
        }
        $token = PlatformRequest::bearerToken($request);
        if ($token === '') {
            throw \app\common\http\ApiProblem::fromEnvelope('Platform authentication is required.', null, 40100);
        }

        try {
            $context = PlatformRuntimeFactory::sessions()->context(
                $token,
                PlatformRequest::requestId($request)
            );
        } catch (AuthException|\DomainException|\InvalidArgumentException) {
            throw \app\common\http\ApiProblem::fromEnvelope('Platform authentication credential is invalid.', null, 40100);
        }

        $operation = sprintf(
            'http.platform.%s.%s',
            strtolower((string)$request->method()),
            trim((string)$request->pathinfo(), '/'),
        );
        return ($this->executionContexts ?? app(ExecutionContextStore::class))->run(
            new \app\common\execution\PlatformExecutionContext($context, $operation),
            static fn() => $next($request),
        );
    }
}
