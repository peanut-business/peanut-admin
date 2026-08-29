<?php
declare(strict_types=1);

namespace app\common\service\module;

use app\common\service\JsonService;
use PeanutAdmin\Kernel\Module\ModuleException;

/** Enforces deployment and Tenant enablement after an upstream identity boundary. */
final class OfficialModuleMiddleware
{
    public function __construct(private readonly ?ModuleExecutionBoundary $modules = null)
    {
    }

    public function handle($request, \Closure $next, string $moduleKey, string $operation)
    {
        try {
            ($this->modules ?? app(ModuleExecutionBoundary::class))->assertHttp($moduleKey, $operation);
        } catch (ModuleException $exception) {
            $status = in_array($exception->errorCode, ['MODULE_NOT_INSTALLED', 'MODULE_INSTALLATION_FAILED'], true)
                ? 50300
                : 40300;
            throw \app\common\http\ApiProblem::fromEnvelope('模块当前不可用', ['error_code' => $exception->errorCode], $status);
        } catch (\Throwable) {
            throw \app\common\http\ApiProblem::fromEnvelope('模块当前不可用', null, 50300);
        }

        return $next($request);
    }
}
