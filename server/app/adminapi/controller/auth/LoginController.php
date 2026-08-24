<?php
declare(strict_types=1);

namespace app\adminapi\controller\auth;

use app\adminapi\controller\BaseAdminController;
use app\adminapi\logic\auth\LoginLogic;
use app\common\dto\authorization\AdminPrincipal;
use app\common\service\authorization\AdminAuthorizationService;
use app\adminapi\service\AdminTokenService;
use app\adminapi\validate\auth\LoginValidate;
use app\common\service\DemoAccountPolicy;

class LoginController extends BaseAdminController
{
    public array $notNeedLogin = ['login', 'logout'];

    public function login()
    {
        $params = $this->request->post();

        // 管理端登录表单提交 username，业务层统一映射为管理员账号。
        $params['account']  = trim((string)($params['account'] ?? $params['username'] ?? ''));
        $params['terminal'] = (int)($params['terminal'] ?? 1);

        $this->validate($params, LoginValidate::class);
        $result = LoginLogic::login($params);

        return $result === false
            ? $this->fail(LoginLogic::getError())
            : $this->data($result);
    }

    public function info()
    {
        $admin = $this->adminInfo;
        if ($admin === []) return $this->fail('管理员不存在');
        $roleNames = array_column($admin['roles'] ?? [], 'name');
        $accessData = (new AdminAuthorizationService())->accessData(
            $this->request->tenantContext,
            AdminPrincipal::fromArray($admin),
        );

        return $this->data([
            'id'          => $admin['id'],
            'username'    => $admin['username'],
            'nickname'    => $admin['nickname'],
            // 管理端展示与鉴权所需字段
            'name'        => $admin['name'],
            'avatar'      => $admin['avatar'],
            // 前端路由守卫用标量 role（'admin' 可访问全部 demo 路由）
            'role'        => $admin['root'] ? 'admin' : 'user',
            'root'        => $admin['root'],
            'roles'       => $roleNames,
            'menu'        => $accessData->menu,
            'permissions' => $accessData->permissions,
            'tenantName' => $admin['tenant_name'],
            'canSwitchTenant' => !($this->request->tenantEntryBound ?? false)
                && ($admin['switchable_tenant_count'] ?? 0) > 1,
            'demoMode' => DemoAccountPolicy::isDemoEmail((string)$admin['username']),
        ]);
    }

    public function logout()
    {
        $token = AdminTokenService::tokenFromRequest($this->request);
        if ($token !== '') {
            LoginLogic::logout($token);
        }

        return $this->success('退出成功');
    }
}
