<?php
declare(strict_types=1);

namespace app\platform\controller;

use app\common\service\storage\StorageConfigurationService;
use app\platform\context\PlatformOperatorContext;

final class PlatformStorageController extends BasePlatformController
{
    public function snapshot()
    {
        return $this->data($this->service()->snapshot());
    }

    public function createAccount()
    {
        return $this->data([
            'id' => $this->service()->createAccount($this->context(), $this->request->post()),
        ]);
    }

    public function updateAccount()
    {
        $this->service()->updateAccount($this->context(), $this->request->post());
        return $this->success('存储账号已更新');
    }

    public function createSpace()
    {
        return $this->data([
            'id' => $this->service()->createSpace($this->context(), $this->request->post()),
        ]);
    }

    public function updateSpace()
    {
        $this->service()->updateSpace($this->context(), $this->request->post());
        return $this->success('Space 已更新');
    }

    public function setRoute()
    {
        $this->service()->setRoute($this->context(), $this->request->post());
        return $this->success('存储路由已更新');
    }

    private function context(): PlatformOperatorContext
    {
        if ($this->platformContext === null) {
            throw \app\common\http\ApiProblem::fromEnvelope(
                'Platform authentication is required.',
                null,
                40100,
            );
        }
        return $this->platformContext;
    }

    private function service(): StorageConfigurationService
    {
        return StorageConfigurationService::fromDefaultConnection();
    }
}
