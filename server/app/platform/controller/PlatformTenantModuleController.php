<?php
declare(strict_types=1);

namespace app\platform\controller;

use app\common\execution\CurrentExecutionContext;
use app\platform\http\PlatformRequest;
use app\platform\service\module\PlatformTenantModuleService;
use app\platform\validate\PlatformTenantModuleValidate;
use DateTimeImmutable;
use think\App;

final class PlatformTenantModuleController extends BasePlatformController
{
    public function __construct(
        App $app,
        CurrentExecutionContext $execution,
        private readonly PlatformTenantModuleService $tenantModules,
    ) {
        parent::__construct($app, $execution);
    }

    public function enable()
    {
        if ($this->platformContext === null) {
            throw \app\common\http\ApiProblem::fromEnvelope('Platform authentication is required.', null, 40100);
        }

        $params = $this->request->post();
        $this->validate($params, PlatformTenantModuleValidate::class . '.enable');
        return $this->data($this->tenantModules->enable(
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
    }

    public function disable()
    {
        if ($this->platformContext === null) {
            throw \app\common\http\ApiProblem::fromEnvelope('Platform authentication is required.', null, 40100);
        }

        $params = $this->request->post();
        $this->validate($params, PlatformTenantModuleValidate::class . '.disable');
        return $this->data($this->tenantModules->disable(
            PlatformRequest::bearerToken($this->request),
            (int)$params['tenant_id'],
            trim((string)$params['module_key']),
            trim((string)$params['change_reason']),
            $this->platformContext->core->requestId
        ));
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
}
