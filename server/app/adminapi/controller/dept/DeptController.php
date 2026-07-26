<?php
declare(strict_types=1);

namespace app\adminapi\controller\dept;

use app\adminapi\controller\BaseAdminController;
use app\adminapi\logic\dept\DeptLogic;
use app\adminapi\validate\dept\DeptValidate;

class DeptController extends BaseAdminController
{
    public function lists()  { return $this->data(DeptLogic::lists()); }
    public function all()    { return $this->data(DeptLogic::all()); }
    public function detail() { return $this->data(DeptLogic::detail((int)$this->request->get('id'))); }

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
        $r = DeptLogic::delete((int)$this->request->post('id'));
        return $r ? $this->success('操作成功') : $this->fail(DeptLogic::getError());
    }

    public function updateStatus()
    {
        DeptLogic::updateStatus((int)$this->request->post('id'), (int)$this->request->post('is_disable', 0));
        return $this->success('操作成功');
    }
}
