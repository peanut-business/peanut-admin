<?php
declare(strict_types=1);

namespace app\adminapi\controller\auth;

use app\adminapi\controller\BaseAdminController;
use app\adminapi\logic\auth\LoginLogic;
use app\adminapi\service\AdminPermissionService;
use app\adminapi\service\AdminTokenService;
use app\adminapi\validate\auth\LoginValidate;
use app\common\model\auth\Admin;

class LoginController extends BaseAdminController
{
    public array $notNeedLogin = ['login', 'logout'];

    public function login()
    {
        $params = $this->request->post();

        // LikeAdmin 使用 account；保留现有 Arco 前端 username 入参兼容。
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
        $admin = Admin::with(['roles'])->findOrEmpty($this->adminId);
        if ($admin->isEmpty()) return $this->fail('管理员不存在');

        $roleNames = $admin->roles->column('name');
        $accessData = AdminPermissionService::accessData($admin);

        return $this->data([
            'id'          => $admin->id,
            'username'    => $admin->username,
            'nickname'    => $admin->nickname,
            // Arco Design Pro Vue 展示与鉴权所需字段
            'name'        => $admin->nickname ?: $admin->username,
            'avatar'      => $admin->avatar,
            // 前端路由守卫用标量 role（'admin' 可访问全部 demo 路由）
            'role'        => $admin->root ? 'admin' : 'user',
            'root'        => $admin->root,
            'roles'       => $roleNames,
            'menu'        => $accessData['menu'],
            'permissions' => $accessData['permissions'],
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
