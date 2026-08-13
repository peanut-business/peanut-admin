<?php
declare(strict_types=1);

namespace app\adminapi\controller\dept;

use app\adminapi\controller\BaseAdminController;
use app\adminapi\logic\dept\JobsLogic;
use app\common\service\org\OrgTenantContext;

class JobsController extends BaseAdminController
{
    public function lists()
    {
        $result = JobsLogic::lists(OrgTenantContext::member($this->request), $this->request->get());
        return $result === false ? $this->fail(JobsLogic::getError()) : $this->data($result);
    }

    public function all()    { return $this->data(JobsLogic::all(OrgTenantContext::member($this->request))); }

    public function detail()
    {
        $params = ['id' => (int)$this->request->get('id')];
        $this->validate($params, ['id' => 'require|integer|gt:0']);
        return $this->data(JobsLogic::detail(OrgTenantContext::member($this->request), $params['id']));
    }

    public function add()
    {
        $params = JobsLogic::normalizeInput($this->request->post());
        $this->validate($params, JobsLogic::validationRules('add'));
        $r = JobsLogic::add(OrgTenantContext::member($this->request), $params);
        return $r ? $this->success('操作成功') : $this->fail(JobsLogic::getError());
    }

    public function edit()
    {
        $params = JobsLogic::normalizeInput($this->request->post());
        $this->validate($params, JobsLogic::validationRules('edit'));
        $r = JobsLogic::edit(OrgTenantContext::member($this->request), $params);
        return $r ? $this->success('操作成功') : $this->fail(JobsLogic::getError());
    }

    public function delete()
    {
        $params = ['id' => (int)$this->request->post('id')];
        $this->validate($params, ['id' => 'require|integer|gt:0']);
        $r = JobsLogic::delete(OrgTenantContext::member($this->request), $params['id']);
        return $r ? $this->success('操作成功') : $this->fail(JobsLogic::getError());
    }

    public function updateStatus()
    {
        $params = JobsLogic::normalizeInput($this->request->post());
        $this->validate($params, ['id' => 'require|integer|gt:0', 'status' => 'require|in:0,1']);
        $r = JobsLogic::updateStatus(OrgTenantContext::member($this->request), (int)$params['id'], (int)$params['status']);
        return $r ? $this->success('操作成功') : $this->fail(JobsLogic::getError());
    }
}
