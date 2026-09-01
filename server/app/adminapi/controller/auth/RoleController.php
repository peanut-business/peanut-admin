<?php
declare(strict_types=1);

namespace app\adminapi\controller\auth;

use think\App;
use app\common\execution\CurrentExecutionContext;

use app\adminapi\controller\BaseAdminController;
use app\adminapi\application\auth\RoleApplicationService;
use app\common\service\org\OrgTenantContext;

class RoleController extends BaseAdminController
{
    public function __construct(App $app, CurrentExecutionContext $executionContext, private readonly RoleApplicationService $roles)
    {
        parent::__construct($app, $executionContext);
    }

    public function lists()
    {
        $result = $this->roles->lists(OrgTenantContext::member(), $this->request->get());
        return $this->data($result);
    }

    public function all()
    {
        return $this->data($this->roles->getAll(OrgTenantContext::member()));
    }

    public function detail()
    {
        $params = $this->request->get();
        $this->validate($params, ['id' => 'require|integer|gt:0']);
        return $this->data($this->roles->detail(OrgTenantContext::member(), (int)$params['id']));
    }

    public function add()
    {
        $params = $this->roleParams();
        $this->validate($params, $this->roles->validationRules('add'));
        $result = $this->roles->add(OrgTenantContext::member(), $params);
        return $result
            ? $this->success('操作成功')
            : $this->fail($this->roles->getError());
    }

    public function edit()
    {
        $params = $this->roleParams();
        $this->validate($params, $this->roles->validationRules('edit'));
        $result = $this->roles->edit(OrgTenantContext::member(), $params);
        return $result
            ? $this->success('操作成功')
            : $this->fail($this->roles->getError());
    }

    public function delete()
    {
        $params = $this->request->post();
        $this->validate($params, ['id' => 'require|integer|gt:0']);
        $result = $this->roles->delete(OrgTenantContext::member(), (int)$params['id']);
        return $result
            ? $this->success('操作成功')
            : $this->fail($this->roles->getError());
    }

    /**
     * menu_id 是正式契约；menu_ids 仅兼容现有 Peanut 前端。
     * 两者同时存在时始终以 menu_id 为准。
     */
    private function roleParams(): array
    {
        $params = $this->request->post();
        if (!array_key_exists('menu_id', $params) && array_key_exists('menu_ids', $params)) {
            $params['menu_id'] = $params['menu_ids'];
        }
        return $params;
    }
}
