<?php
declare(strict_types=1);

namespace app\adminapi\controller\auth;

use app\adminapi\controller\BaseAdminController;
use app\adminapi\logic\auth\MenuLogic;
use app\adminapi\validate\auth\MenuValidate;
use app\common\service\instance\InstanceToolAccessGuard;
use app\common\service\JsonService;
use app\common\service\org\OrgTenantContext;
use think\response\Json;

class MenuController extends BaseAdminController
{
    public function route()  { return $this->data(MenuLogic::getMenuByAdminId($this->request->tenantContext ?? null, $this->adminId)); }
    public function lists()
    {
        return $this->instanceMenuDenial() ?? $this->data(MenuLogic::getAll());
    }
    public function all()    { return $this->data(MenuLogic::getAllSimple(OrgTenantContext::member($this->request))); }
    public function detail()
    {
        if ($denial = $this->instanceMenuDenial()) return $denial;
        $params = ['id' => (int)$this->request->get('id')];
        $this->validate($params, MenuValidate::class . '.detail');
        return $this->data(MenuLogic::detail($params['id']));
    }

    public function add()
    {
        if ($denial = $this->instanceMenuDenial()) return $denial;
        $this->validate($this->request->post(), MenuValidate::class . '.add');
        $result = MenuLogic::add($this->request->post());
        return $result ? $this->success('操作成功') : $this->fail(MenuLogic::getError());
    }

    public function edit()
    {
        if ($denial = $this->instanceMenuDenial()) return $denial;
        $this->validate($this->request->post(), MenuValidate::class . '.edit');
        $result = MenuLogic::edit($this->request->post());
        return $result ? $this->success('操作成功') : $this->fail(MenuLogic::getError());
    }

    public function delete()
    {
        if ($denial = $this->instanceMenuDenial()) return $denial;
        $params = ['id' => (int)$this->request->post('id')];
        $this->validate($params, MenuValidate::class . '.delete');
        $result = MenuLogic::delete($params['id']);
        return $result ? $this->success('操作成功') : $this->fail(MenuLogic::getError());
    }

    public function updateStatus()
    {
        if ($denial = $this->instanceMenuDenial()) return $denial;
        $params = [
            'id' => (int)$this->request->post('id'),
            'is_disable' => $this->request->post('is_disable'),
        ];
        $this->validate($params, MenuValidate::class . '.status');
        $result = MenuLogic::updateStatus($params['id'], (int)$params['is_disable']);
        return $result ? $this->success('操作成功') : $this->fail(MenuLogic::getError());
    }

    private function instanceMenuDenial(): ?Json
    {
        $guard = InstanceToolAccessGuard::fromConfiguredValue(config('deployment.mode'));
        return $guard->allows()
            ? null
            : JsonService::fail('实例级菜单管理仅在 standalone 部署中可用', null, 40300);
    }
}
