<?php
declare(strict_types=1);

namespace app\adminapi\controller\dept;

use app\adminapi\controller\BaseAdminController;
use app\adminapi\logic\dept\JobsLogic;
use app\adminapi\validate\dept\JobsValidate;

class JobsController extends BaseAdminController
{
    public function lists()
    {
        $res = JobsLogic::lists($this->request->get());
        return $this->dataLists($res['lists'], $res['count'], $res['pageNo'], $res['pageSize']);
    }

    public function all()    { return $this->data(JobsLogic::all()); }
    public function detail() { return $this->data(JobsLogic::detail((int)$this->request->get('id'))); }

    public function add()
    {
        $this->validate($this->request->post(), JobsValidate::class . '.add');
        $r = JobsLogic::add($this->request->post());
        return $r ? $this->success('操作成功') : $this->fail(JobsLogic::getError());
    }

    public function edit()
    {
        $this->validate($this->request->post(), JobsValidate::class . '.edit');
        $r = JobsLogic::edit($this->request->post());
        return $r ? $this->success('操作成功') : $this->fail(JobsLogic::getError());
    }

    public function delete()
    {
        JobsLogic::delete((int)$this->request->post('id'));
        return $this->success('操作成功');
    }

    public function updateStatus()
    {
        JobsLogic::updateStatus((int)$this->request->post('id'), (int)$this->request->post('is_disable', 0));
        return $this->success('操作成功');
    }
}
