<?php
declare(strict_types=1);

namespace app\adminapi\controller\auth;

use app\adminapi\controller\BaseAdminController;
use app\adminapi\logic\auth\AdminLogic;
use app\adminapi\validate\auth\AdminValidate;
use app\adminapi\validate\auth\EditSelfValidate;
use app\common\service\org\OrgTenantContext;

class AdminController extends BaseAdminController
{
    public function lists()
    {
        $params = AdminLogic::normalizeInput($this->request->get());
        $this->validate($params, AdminValidate::class . '.lists');
        $result = AdminLogic::lists(OrgTenantContext::member($this->request), $params);
        return $result === false ? $this->fail(AdminLogic::getError()) : $this->data($result);
    }

    public function detail()
    {
        $params = ['id' => (int)$this->request->get('id')];
        $this->validate($params, ['id' => 'require|integer|gt:0']);
        return $this->data(AdminLogic::detail(OrgTenantContext::member($this->request), $params['id']));
    }

    public function self()   { return $this->data(AdminLogic::detail(OrgTenantContext::member($this->request), $this->adminId)); }
    public function editSelf() { $this->validate($this->request->post(), EditSelfValidate::class); $r = AdminLogic::editSelf(OrgTenantContext::member($this->request), $this->adminId, $this->request->post()); return $r ? $this->success('操作成功') : $this->fail(AdminLogic::getError()); }

    public function add()
    {
        $params = AdminLogic::normalizeInput($this->request->post());
        $this->validate($params, AdminLogic::validationRules('add'));
        $result = AdminLogic::add(OrgTenantContext::member($this->request), $params);
        return $result ? $this->success('操作成功') : $this->fail(AdminLogic::getError());
    }

    public function edit()
    {
        $params = AdminLogic::normalizeInput($this->request->post());
        $this->validate($params, AdminLogic::validationRules('edit'));
        $result = AdminLogic::edit(OrgTenantContext::member($this->request), $params);
        return $result ? $this->success('操作成功') : $this->fail(AdminLogic::getError());
    }

    public function delete()
    {
        $params = ['id' => (int)$this->request->post('id')];
        $this->validate($params, ['id' => 'require|integer|gt:0']);
        $result = AdminLogic::delete(OrgTenantContext::member($this->request), $params['id'], $this->adminId);
        return $result ? $this->success('操作成功') : $this->fail(AdminLogic::getError());
    }

    public function updateStatus()
    {
        $params = [
            'id' => (int)$this->request->post('id'),
            'disable' => $this->request->post('disable'),
        ];
        $this->validate($params, ['id' => 'require|integer|gt:0', 'disable' => 'require|in:0,1']);
        $result = AdminLogic::updateStatus(OrgTenantContext::member($this->request), $params['id'], (int)$params['disable'], $this->adminId);
        return $result ? $this->success('操作成功') : $this->fail(AdminLogic::getError());
    }
}
