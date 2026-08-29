<?php
declare(strict_types=1);

namespace app\common\http;

use app\common\application\BusinessException;
use app\common\service\installation\InstallationExecutionException;
use app\common\service\http\OutboundHttpException;
use app\common\service\storage\StorageProviderException;
use app\platform\invitation\TenantOwnerInvitationException;
use PeanutAdmin\Kernel\Auth\AuthException;
use PeanutAdmin\Kernel\Authorization\Application\AdminAccessException;
use PeanutAdmin\Kernel\Module\ModuleException;
use PeanutAdmin\OpsConsole\Application\OpsConsoleException;
use think\exception\ValidateException;

/** Maps stable domain failures into the single public API error type. */
final class ApiProblemMapper
{
    public function map(\Throwable $exception): ?ApiProblem
    {
        return match (true) {
            $exception instanceof ApiProblem => $exception,
            $exception instanceof BusinessException => new ApiProblem(
                $exception->errorCode,
                $exception->httpStatus,
                $exception->getMessage(),
            ),
            $exception instanceof OutboundHttpException => new ApiProblem(
                OutboundHttpException::ERROR_CODE,
                OutboundHttpException::HTTP_STATUS,
                $exception->getMessage(),
            ),
            $exception instanceof StorageProviderException => new ApiProblem(
                $exception->errorCode,
                $exception->httpStatus,
                $exception->getMessage(),
            ),
            $exception instanceof ValidateException => ApiProblem::fromEnvelope(
                (string)$exception->getError(),
            ),
            $exception instanceof AuthException => new ApiProblem(
                $exception->errorCode,
                $exception->httpStatus,
                $exception->getMessage(),
            ),
            $exception instanceof AdminAccessException => new ApiProblem(
                $exception->errorCode,
                $exception->httpStatus,
                $exception->getMessage(),
            ),
            $exception instanceof TenantOwnerInvitationException => new ApiProblem(
                $exception->errorCode,
                $exception->httpStatus,
                $exception->getMessage(),
            ),
            $exception instanceof InstallationExecutionException => new ApiProblem(
                $exception->errorCode,
                $exception->httpStatus,
                $exception->getMessage(),
            ),
            $exception instanceof OpsConsoleException => new ApiProblem(
                $exception->problemCode,
                $exception->status,
                'Operations request was rejected.',
            ),
            $exception instanceof ModuleException => new ApiProblem(
                $exception->errorCode,
                $this->moduleStatus($exception->errorCode),
                'Module request was rejected.',
            ),
            default => null,
        };
    }

    private function moduleStatus(string $errorCode): int
    {
        return match ($errorCode) {
            'MODULE_TENANT_DISABLED',
            'MODULE_OPERATION_NOT_ALLOWED' => 403,
            'MODULE_NOT_INSTALLED',
            'MODULE_INSTALLATION_FAILED',
            'MODULE_REGISTRY_UNAVAILABLE' => 503,
            default => 409,
        };
    }
}
