<?php
declare(strict_types=1);

namespace app\adminapi\controller\auth;

use app\adminapi\controller\BaseAdminController;
use app\adminapi\logic\auth\AdminLogic;
use app\adminapi\validate\auth\AdminValidate;
use app\adminapi\validate\auth\EditSelfValidate;

class AdminController extends BaseAdminController
{
    public function lists()
    {
        $params = AdminLogic::normalizeInput($this->request->get());
        $this->validate($params, AdminValidate::class . '.lists');
        $result = AdminLogic::lists($params);
        return $result === false ? $this->fail(AdminLogic::getError()) : $this->data($result);
    }

    public function detail()
    {
        $params = ['id' => (int)$this->request->get('id')];
        $this->validate($params, AdminValidate::class . '.detail');
        return $this->data(AdminLogic::detail($params['id']));
    }

    public function self()   { return $this->data(AdminLogic::detail($this->adminId)); }
    public function editSelf() { $this->validate($this->request->post(), EditSelfValidate::class); $r = AdminLogic::editSelf($this->adminId, $this->request->post()); return $r ? $this->success('操作成功') : $this->fail(AdminLogic::getError()); }

    public function add()
    {
        $params = AdminLogic::normalizeInput($this->request->post());
        $this->validate($params, AdminValidate::class . '.add');
        $result = AdminLogic::add($params);
        return $result ? $this->success('操作成功') : $this->fail(AdminLogic::getError());
    }

    public function edit()
    {
        $params = AdminLogic::normalizeInput($this->request->post());
        $this->validate($params, AdminValidate::class . '.edit');
        $result = AdminLogic::edit($params);
        return $result ? $this->success('操作成功') : $this->fail(AdminLogic::getError());
    }

    public function delete()
    {
        $params = ['id' => (int)$this->request->post('id')];
        $this->validate($params, AdminValidate::class . '.delete');
        $result = AdminLogic::delete($params['id'], $this->adminId);
        return $result ? $this->success('操作成功') : $this->fail(AdminLogic::getError());
    }

    public function updateStatus()
    {
        $params = [
            'id' => (int)$this->request->post('id'),
            'disable' => $this->request->post('disable'),
        ];
        $this->validate($params, AdminValidate::class . '.status');
        $result = AdminLogic::updateStatus($params['id'], (int)$params['disable'], $this->adminId);
        return $result ? $this->success('操作成功') : $this->fail(AdminLogic::getError());
    }
}
