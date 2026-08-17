<?php
declare(strict_types=1);

namespace app\platform\controller;

use app\common\service\JsonService;
use app\platform\service\PlatformRuntimeFactory;
use app\platform\validate\TenantEntryBindingValidate;
use PeanutAdmin\Kernel\Authorization\Application\AdminAccessException;

final class PlatformTenantEntryBindingController extends BasePlatformController
{
    public function lists()
    {
        if ($this->platformContext === null) {
            return JsonService::fail('Platform authentication is required.', null, 40100);
        }
        $tenantId = trim((string)$this->request->get('tenant_id', ''));
        try {
            return $this->data(PlatformRuntimeFactory::tenantEntryBindings()->lists(
                $this->platformContext,
                $tenantId === '' ? null : (int)$tenantId
            ));
        } catch (AdminAccessException $exception) {
            return $this->accessFailure($exception);
        }
    }

    public function enable()
    {
        return $this->mutate('enable', fn(array $params): array =>
            PlatformRuntimeFactory::tenantEntryBindings()->enable(
                $this->platformContext,
                (int)$params['tenant_id'],
                (string)$params['host'],
                (string)$params['client_key'],
                (string)$params['change_reason']
            )
        );
    }

    public function disable()
    {
        return $this->mutate('disable', fn(array $params): array =>
            PlatformRuntimeFactory::tenantEntryBindings()->disable(
                $this->platformContext,
                (int)$params['binding_id'],
                (string)$params['change_reason']
            )
        );
    }

    private function mutate(string $scene, callable $operation)
    {
        if ($this->platformContext === null) {
            return JsonService::fail('Platform authentication is required.', null, 40100);
        }
        $params = $this->request->post();
        $this->validate($params, TenantEntryBindingValidate::class . '.' . $scene);
        try {
            return $this->data($operation($params));
        } catch (AdminAccessException $exception) {
            return $this->accessFailure($exception);
        } catch (\DomainException $exception) {
            return JsonService::fail(
                'Tenant entry binding request was rejected.',
                ['error_code' => $exception->getMessage()],
                40900
            );
        }
    }

    private function accessFailure(AdminAccessException $exception)
    {
        return JsonService::fail(
            $exception->getMessage(),
            ['error_code' => $exception->errorCode],
            $exception->httpStatus * 100
        );
    }
}
