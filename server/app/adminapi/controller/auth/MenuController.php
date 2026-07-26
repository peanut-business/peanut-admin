<?php
declare(strict_types=1);

namespace app\adminapi\controller\auth;

use app\adminapi\controller\BaseAdminController;
use app\adminapi\logic\auth\MenuLogic;
use app\adminapi\validate\auth\MenuValidate;

class MenuController extends BaseAdminController
{
    public function route()  { return $this->data(MenuLogic::getMenuByAdminId($this->adminId)); }
    public function lists()  { return $this->data(MenuLogic::getAll()); }
    public function all()    { return $this->data(MenuLogic::getAllSimple()); }
    public function detail() { return $this->data(MenuLogic::detail((int)$this->request->get('id'))); }
    public function add()    { $this->checkParams(MenuValidate::class, 'add');  $r = MenuLogic::add($this->request->post()); return $r ? $this->success('操作成功') : $this->fail(MenuLogic::getError()); }
    public function edit()   { $this->checkParams(MenuValidate::class, 'edit'); $r = MenuLogic::edit($this->request->post()); return $r ? $this->success('操作成功') : $this->fail(MenuLogic::getError()); }
    public function delete() { MenuLogic::delete((int)$this->request->post('id')); return $this->success('操作成功'); }
    public function updateStatus() { MenuLogic::updateStatus((int)$this->request->post('id'), (int)$this->request->post('is_disable', 0)); return $this->success('操作成功'); }
}
