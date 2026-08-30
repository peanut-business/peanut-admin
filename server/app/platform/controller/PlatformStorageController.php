<?php
declare(strict_types=1);
namespace app\platform\controller;
use app\common\service\storage\StorageConfigurationService;
final class PlatformStorageController extends BasePlatformController
{
    public function snapshot(){return $this->data($this->service()->snapshot());}
    public function createAccount(){return $this->data(['id'=>$this->service()->createAccount($this->request->post())]);}
    public function updateAccount(){$this->service()->updateAccount($this->request->post());return $this->success('存储账号已更新');}
    public function createSpace(){return $this->data(['id'=>$this->service()->createSpace($this->request->post())]);}
    public function updateSpace(){$this->service()->updateSpace($this->request->post());return $this->success('Space 已更新');}
    public function setRoute()
    {
        try {
            $this->service()->setRoute($this->request->post());
        } catch (\InvalidArgumentException $exception) {
            throw \app\common\http\ApiProblem::fromEnvelope(
                $exception->getMessage(),
                ['error_code' => 'STORAGE_ROUTE_INPUT_INVALID'],
                42200,
            );
        }
        return $this->success('存储路由已更新');
    }
    private function service():StorageConfigurationService{return StorageConfigurationService::fromDefaultConnection();}
}
