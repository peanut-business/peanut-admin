<?php
declare(strict_types=1);

namespace app\platform\controller;

use app\common\service\JsonService;
use app\platform\http\PlatformRequest;
use app\platform\service\PlatformRuntimeFactory;
use app\platform\validate\PlatformTenantModuleValidate;
use DateTimeImmutable;
use PeanutAdmin\Kernel\Authorization\Application\AdminAccessException;
use PeanutAdmin\Kernel\Module\ModuleException;

final class PlatformTenantModuleController extends BasePlatformController
{
    public function enable()
    {
        if ($this->platformContext === null) {
            return JsonService::fail('Platform authentication is required.', null, 40100);
        }

        $params = $this->request->post();
        $this->validate($params, PlatformTenantModuleValidate::class . '.enable');
        try {
            return $this->data(PlatformRuntimeFactory::tenantModules()->enable(
                PlatformRequest::bearerToken($this->request),
                (int)$params['tenant_id'],
                trim((string)$params['module_key']),
                is_array($params['config'] ?? null) ? $params['config'] : [],
                'manual',
                $this->optionalDate($params['effective_at'] ?? null),
                $this->optionalDate($params['expires_at'] ?? null),
                trim((string)$params['change_reason']),
                $this->platformContext->core->requestId
            ));
        } catch (AdminAccessException $exception) {
            return $this->accessFailure($exception);
        } catch (ModuleException $exception) {
            return $this->moduleFailure($exception);
        } catch (\Exception) {
            return JsonService::fail(
                'Tenant Module request is invalid.',
                ['error_code' => 'MODULE_REQUEST_INVALID'],
                42200
            );
        }
    }

    public function disable()
    {
        if ($this->platformContext === null) {
            return JsonService::fail('Platform authentication is required.', null, 40100);
        }

        $params = $this->request->post();
        $this->validate($params, PlatformTenantModuleValidate::class . '.disable');
        try {
            return $this->data(PlatformRuntimeFactory::tenantModules()->disable(
                PlatformRequest::bearerToken($this->request),
                (int)$params['tenant_id'],
                trim((string)$params['module_key']),
                trim((string)$params['change_reason']),
                $this->platformContext->core->requestId
            ));
        } catch (AdminAccessException $exception) {
            return $this->accessFailure($exception);
        } catch (ModuleException $exception) {
            return $this->moduleFailure($exception);
        }
    }

    private function optionalDate(mixed $value): ?DateTimeImmutable
    {
        $candidate = trim((string)$value);
        if ($candidate === '') {
            return null;
        }
        $date = DateTimeImmutable::createFromFormat(DateTimeImmutable::ATOM, $candidate);
        if (!$date instanceof DateTimeImmutable || $date->format(DateTimeImmutable::ATOM) !== $candidate) {
            throw new \InvalidArgumentException('Invalid date.');
        }
        return $date;
    }

    private function accessFailure(AdminAccessException $exception)
    {
        return JsonService::fail(
            $exception->getMessage(),
            ['error_code' => $exception->errorCode],
            $exception->httpStatus * 100
        );
    }

    private function moduleFailure(ModuleException $exception)
    {
        $status = $exception->errorCode === 'MODULE_REGISTRY_UNAVAILABLE' ? 503 : 409;
        return JsonService::fail(
            'Tenant Module request was rejected.',
            ['error_code' => $exception->errorCode],
            $status * 100
        );
    }
}
