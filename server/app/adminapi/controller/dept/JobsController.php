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
        $result = JobsLogic::lists($this->request->get());
        return $result === false ? $this->fail(JobsLogic::getError()) : $this->data($result);
    }

    public function all()    { return $this->data(JobsLogic::all()); }

    public function detail()
    {
        $params = ['id' => (int)$this->request->get('id')];
        $this->validate($params, JobsValidate::class . '.detail');
        return $this->data(JobsLogic::detail($params['id']));
    }

    public function add()
    {
        $params = JobsLogic::normalizeInput($this->request->post());
        $this->validate($params, JobsValidate::class . '.add');
        $r = JobsLogic::add($params);
        return $r ? $this->success('操作成功') : $this->fail(JobsLogic::getError());
    }

    public function edit()
    {
        $params = JobsLogic::normalizeInput($this->request->post());
        $this->validate($params, JobsValidate::class . '.edit');
        $r = JobsLogic::edit($params);
        return $r ? $this->success('操作成功') : $this->fail(JobsLogic::getError());
    }

    public function delete()
    {
        $params = ['id' => (int)$this->request->post('id')];
        $this->validate($params, JobsValidate::class . '.delete');
        $r = JobsLogic::delete($params['id']);
        return $r ? $this->success('操作成功') : $this->fail(JobsLogic::getError());
    }

    public function updateStatus()
    {
        $params = JobsLogic::normalizeInput($this->request->post());
        $this->validate($params, JobsValidate::class . '.status');
        $r = JobsLogic::updateStatus((int)$params['id'], (int)$params['status']);
        return $r ? $this->success('操作成功') : $this->fail(JobsLogic::getError());
    }
}
