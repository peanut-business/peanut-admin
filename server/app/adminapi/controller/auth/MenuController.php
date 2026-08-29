<?php
declare(strict_types=1);

namespace app\adminapi\controller\auth;

use think\App;

use app\adminapi\controller\BaseAdminController;
use app\adminapi\application\auth\MenuApplicationService;
use app\adminapi\validate\auth\MenuValidate;
use app\common\service\instance\InstanceToolAccessGuard;
use app\common\service\JsonService;
use app\common\service\org\OrgTenantContext;
use think\response\Json;
use app\common\execution\ExecutionContextAccess;

class MenuController extends BaseAdminController
{
    public function __construct(App $app, private readonly MenuApplicationService $menus)
    {
        parent::__construct($app);
    }

    public function route()  { return $this->data($this->menus->getMenuByAdminId(ExecutionContextAccess::tenantAdmin(), $this->adminId)); }
    public function lists()
    {
        return $this->instanceMenuDenial() ?? $this->data($this->menus->getAll());
    }
    public function all()    { return $this->data($this->menus->getAllSimple(OrgTenantContext::member())); }
    public function detail()
    {
        if ($denial = $this->instanceMenuDenial()) return $denial;
        $params = ['id' => (int)$this->request->get('id')];
        $this->validate($params, MenuValidate::class . '.detail');
        return $this->data($this->menus->detail($params['id']));
    }

    public function add()
    {
        if ($denial = $this->instanceMenuDenial()) return $denial;
        $this->validate($this->request->post(), MenuValidate::class . '.add');
        $result = $this->menus->add($this->request->post());
        return $result ? $this->success('操作成功') : $this->fail($this->menus->getError());
    }

    public function edit()
    {
        if ($denial = $this->instanceMenuDenial()) return $denial;
        $this->validate($this->request->post(), MenuValidate::class . '.edit');
        $result = $this->menus->edit($this->request->post());
        return $result ? $this->success('操作成功') : $this->fail($this->menus->getError());
    }

    public function delete()
    {
        if ($denial = $this->instanceMenuDenial()) return $denial;
        $params = ['id' => (int)$this->request->post('id')];
        $this->validate($params, MenuValidate::class . '.delete');
        $result = $this->menus->delete($params['id']);
        return $result ? $this->success('操作成功') : $this->fail($this->menus->getError());
    }

    public function updateStatus()
    {
        if ($denial = $this->instanceMenuDenial()) return $denial;
        $params = [
            'id' => (int)$this->request->post('id'),
            'is_disable' => $this->request->post('is_disable'),
        ];
        $this->validate($params, MenuValidate::class . '.status');
        $result = $this->menus->updateStatus($params['id'], (int)$params['is_disable']);
        return $result ? $this->success('操作成功') : $this->fail($this->menus->getError());
    }

    private function instanceMenuDenial(): ?Json
    {
        $guard = InstanceToolAccessGuard::fromConfiguredValue(config('deployment.mode'));
        return $guard->allows()
            ? null
            : throw \app\common\http\ApiProblem::fromEnvelope('实例级菜单管理仅在 standalone 部署中可用', null, 40300);
    }
}
