<?php
declare(strict_types=1);

namespace app\adminapi\controller\auth;

use app\adminapi\controller\BaseAdminController;
use app\adminapi\logic\auth\MenuLogic;
use app\adminapi\validate\auth\MenuValidate;

class MenuController extends BaseAdminController
{
    public function route()  { return $this->data(MenuLogic::getMenuByAdminId($this->request->tenantContext ?? null, $this->adminId)); }
    public function lists()  { return $this->data(MenuLogic::getAll()); }
    public function all()    { return $this->data(MenuLogic::getAllSimple()); }
    public function detail() { $params = ['id' => (int)$this->request->get('id')]; $this->validate($params, MenuValidate::class . '.detail'); return $this->data(MenuLogic::detail($params['id'])); }
    public function add()    { $this->validate($this->request->post(), MenuValidate::class . '.add');  $r = MenuLogic::add($this->request->post()); return $r ? $this->success('操作成功') : $this->fail(MenuLogic::getError()); }
    public function edit()   { $this->validate($this->request->post(), MenuValidate::class . '.edit'); $r = MenuLogic::edit($this->request->post()); return $r ? $this->success('操作成功') : $this->fail(MenuLogic::getError()); }
    public function delete() { $params = ['id' => (int)$this->request->post('id')]; $this->validate($params, MenuValidate::class . '.delete'); $r = MenuLogic::delete($params['id']); return $r ? $this->success('操作成功') : $this->fail(MenuLogic::getError()); }
    public function updateStatus() { $params = ['id' => (int)$this->request->post('id'), 'is_disable' => $this->request->post('is_disable')]; $this->validate($params, MenuValidate::class . '.status'); $r = MenuLogic::updateStatus($params['id'], (int)$params['is_disable']); return $r ? $this->success('操作成功') : $this->fail(MenuLogic::getError()); }
}
