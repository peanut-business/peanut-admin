<?php
declare(strict_types=1);

namespace app\adminapi\controller\dept;

use app\adminapi\controller\BaseAdminController;
use app\adminapi\logic\dept\DeptLogic;
use app\common\service\org\OrgTenantContext;

class DeptController extends BaseAdminController
{
    public function lists()  { return $this->data(DeptLogic::lists(OrgTenantContext::member($this->request), $this->request->get())); }
    public function all()    { return $this->data(DeptLogic::all(OrgTenantContext::member($this->request))); }

    public function leaderDept()
    {
        return $this->data(DeptLogic::leaderDept(OrgTenantContext::member($this->request)));
    }

    public function detail()
    {
        $this->validate($this->request->get(), ['id' => 'require|integer|gt:0']);
        return $this->data(DeptLogic::detail(OrgTenantContext::member($this->request), (int)$this->request->get('id')));
    }

    public function add()
    {
        $this->validate($this->request->post(), DeptLogic::validationRules('add'));
        $r = DeptLogic::add(OrgTenantContext::member($this->request), $this->request->post());
        return $r ? $this->success('操作成功') : $this->fail(DeptLogic::getError());
    }

    public function edit()
    {
        $this->validate($this->request->post(), DeptLogic::validationRules('edit'));
        $r = DeptLogic::edit(OrgTenantContext::member($this->request), $this->request->post());
        return $r ? $this->success('操作成功') : $this->fail(DeptLogic::getError());
    }

    public function delete()
    {
        $this->validate($this->request->post(), ['id' => 'require|integer|gt:0']);
        $r = DeptLogic::delete(OrgTenantContext::member($this->request), (int)$this->request->post('id'));
        return $r ? $this->success('操作成功') : $this->fail(DeptLogic::getError());
    }

    public function updateStatus()
    {
        $params = $this->request->post();
        if (!array_key_exists('status', $params) && array_key_exists('is_disable', $params)) {
            $params['status'] = (int)$params['is_disable'] === 0 ? 1 : 0;
        }
        $this->validate($params, ['id' => 'require|integer|gt:0', 'status' => 'require|in:0,1']);
        $r = DeptLogic::updateStatus(OrgTenantContext::member($this->request), (int)$params['id'], (int)$params['status']);
        return $r ? $this->success('操作成功') : $this->fail(DeptLogic::getError());
    }
}
