<?php
declare(strict_types=1);

namespace app\adminapi\controller\dept;

use app\adminapi\controller\BaseAdminController;
use app\adminapi\logic\dept\DeptLogic;
use app\adminapi\validate\dept\DeptValidate;

class DeptController extends BaseAdminController
{
    public function lists()  { return $this->data(DeptLogic::lists($this->request->get())); }
    public function all()    { return $this->data(DeptLogic::all()); }

    public function leaderDept()
    {
        return $this->data(DeptLogic::leaderDept());
    }

    public function detail()
    {
        $this->validate($this->request->get(), DeptValidate::class . '.detail');
        return $this->data(DeptLogic::detail((int)$this->request->get('id')));
    }

    public function add()
    {
        $this->validate($this->request->post(), DeptValidate::class . '.add');
        $r = DeptLogic::add($this->request->post());
        return $r ? $this->success('操作成功') : $this->fail(DeptLogic::getError());
    }

    public function edit()
    {
        $this->validate($this->request->post(), DeptValidate::class . '.edit');
        $r = DeptLogic::edit($this->request->post());
        return $r ? $this->success('操作成功') : $this->fail(DeptLogic::getError());
    }

    public function delete()
    {
        $this->validate($this->request->post(), DeptValidate::class . '.delete');
        $r = DeptLogic::delete((int)$this->request->post('id'));
        return $r ? $this->success('操作成功') : $this->fail(DeptLogic::getError());
    }

    public function updateStatus()
    {
        $params = $this->request->post();
        if (!array_key_exists('status', $params) && array_key_exists('is_disable', $params)) {
            $params['status'] = (int)$params['is_disable'] === 0 ? 1 : 0;
        }
        $this->validate($params, DeptValidate::class . '.status');
        $r = DeptLogic::updateStatus((int)$params['id'], (int)$params['status']);
        return $r ? $this->success('操作成功') : $this->fail(DeptLogic::getError());
    }
}
