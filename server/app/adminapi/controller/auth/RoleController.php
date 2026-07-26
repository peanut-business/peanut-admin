<?php
declare(strict_types=1);

namespace app\adminapi\controller\auth;

use app\adminapi\controller\BaseAdminController;
use app\adminapi\logic\auth\RoleLogic;
use app\adminapi\validate\auth\RoleValidate;

class RoleController extends BaseAdminController
{
    public function lists()  { return $this->data(RoleLogic::getAll()); }
    public function all()    { return $this->data(RoleLogic::getAll()); }
    public function detail() { return $this->data(RoleLogic::detail((int)$this->request->get('id'))); }
    public function add()    { $this->checkParams(RoleValidate::class, 'add');  $r = RoleLogic::add($this->request->post()); return $r ? $this->success('操作成功') : $this->fail(RoleLogic::getError()); }
    public function edit()   { $this->checkParams(RoleValidate::class, 'edit'); $r = RoleLogic::edit($this->request->post()); return $r ? $this->success('操作成功') : $this->fail(RoleLogic::getError()); }
    public function delete() { RoleLogic::delete((int)$this->request->post('id')); return $this->success('操作成功'); }
}
