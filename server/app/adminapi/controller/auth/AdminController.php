<?php
declare(strict_types=1);

namespace app\adminapi\controller\auth;

use think\App;
use app\common\execution\CurrentExecutionContext;

use app\adminapi\controller\BaseAdminController;
use app\adminapi\application\auth\AdminApplicationService;
use app\adminapi\validate\auth\AdminValidate;
use app\adminapi\validate\auth\EditSelfValidate;
use app\common\service\org\OrgTenantContext;

class AdminController extends BaseAdminController
{
    public function __construct(App $app, CurrentExecutionContext $executionContext, private readonly AdminApplicationService $admins)
    {
        parent::__construct($app, $executionContext);
    }

    public function lists()
    {
        $params = $this->admins->normalizeInput($this->request->get());
        $this->validate($params, AdminValidate::class . '.lists');
        $result = $this->admins->lists(OrgTenantContext::member(), $params);
        return $result === false ? $this->fail($this->admins->getError()) : $this->data($result);
    }

    public function detail()
    {
        $params = ['id' => (int)$this->request->get('id')];
        $this->validate($params, ['id' => 'require|integer|gt:0']);
        return $this->data($this->admins->detail(OrgTenantContext::member(), $params['id']));
    }

    public function self()   { return $this->data($this->admins->detail(OrgTenantContext::member(), $this->adminId)); }
    public function editSelf() { $this->validate($this->request->post(), EditSelfValidate::class); $r = $this->admins->editSelf(OrgTenantContext::member(), $this->adminId, $this->request->post()); return $r ? $this->success('操作成功') : $this->fail($this->admins->getError()); }

    public function add()
    {
        $params = $this->admins->normalizeInput($this->request->post());
        $this->validate($params, $this->admins->validationRules('add'));
        $result = $this->admins->add(OrgTenantContext::member(), $params);
        return $result ? $this->success('操作成功') : $this->fail($this->admins->getError());
    }

    public function edit()
    {
        $params = $this->admins->normalizeInput($this->request->post());
        $this->validate($params, $this->admins->validationRules('edit'));
        $result = $this->admins->edit(OrgTenantContext::member(), $params);
        return $result ? $this->success('操作成功') : $this->fail($this->admins->getError());
    }

    public function delete()
    {
        $params = ['id' => (int)$this->request->post('id')];
        $this->validate($params, ['id' => 'require|integer|gt:0']);
        $result = $this->admins->delete(OrgTenantContext::member(), $params['id'], $this->adminId);
        return $result ? $this->success('操作成功') : $this->fail($this->admins->getError());
    }

    public function updateStatus()
    {
        $params = [
            'id' => (int)$this->request->post('id'),
            'disable' => $this->request->post('disable'),
        ];
        $this->validate($params, ['id' => 'require|integer|gt:0', 'disable' => 'require|in:0,1']);
        $result = $this->admins->updateStatus(OrgTenantContext::member(), $params['id'], (int)$params['disable'], $this->adminId);
        return $result ? $this->success('操作成功') : $this->fail($this->admins->getError());
    }
}
