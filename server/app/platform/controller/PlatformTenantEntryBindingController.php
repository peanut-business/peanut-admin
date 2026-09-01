<?php
declare(strict_types=1);

namespace app\platform\controller;

use app\common\execution\CurrentExecutionContext;
use app\common\service\JsonService;
use app\platform\service\TenantEntryBindingAdminService;
use app\platform\validate\TenantEntryBindingValidate;
use PeanutAdmin\Kernel\Authorization\Application\AdminAccessException;
use think\App;

final class PlatformTenantEntryBindingController extends BasePlatformController
{
    public function __construct(
        App $app,
        CurrentExecutionContext $execution,
        private readonly TenantEntryBindingAdminService $entryBindings,
    ) {
        parent::__construct($app, $execution);
    }

    public function lists()
    {
        if ($this->platformContext === null) {
            throw \app\common\http\ApiProblem::fromEnvelope('Platform authentication is required.', null, 40100);
        }
        $tenantId = trim((string)$this->request->get('tenant_id', ''));
        try {
            return $this->data($this->entryBindings->lists(
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
            $this->entryBindings->enable(
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
            $this->entryBindings->disable(
                $this->platformContext,
                (int)$params['binding_id'],
                (string)$params['change_reason']
            )
        );
    }

    private function mutate(string $scene, callable $operation)
    {
        if ($this->platformContext === null) {
            throw \app\common\http\ApiProblem::fromEnvelope('Platform authentication is required.', null, 40100);
        }
        $params = $this->request->post();
        $this->validate($params, TenantEntryBindingValidate::class . '.' . $scene);
        try {
            return $this->data($operation($params));
        } catch (AdminAccessException $exception) {
            return $this->accessFailure($exception);
        } catch (\DomainException $exception) {
            throw \app\common\http\ApiProblem::fromEnvelope(
                'Tenant entry binding request was rejected.',
                ['error_code' => $exception->getMessage()],
                40900
            );
        }
    }

    private function accessFailure(AdminAccessException $exception)
    {
        throw \app\common\http\ApiProblem::fromEnvelope(
            $exception->getMessage(),
            ['error_code' => $exception->errorCode],
            $exception->httpStatus * 100
        );
    }
}
