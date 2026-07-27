<?php
declare(strict_types=1);

namespace app\adminapi\controller\auth;

use app\adminapi\controller\BaseAdminController;
use app\adminapi\service\AdminTokenService;
use app\common\model\auth\Admin;

class LoginController extends BaseAdminController
{
    public array $notNeedLogin = ['login', 'logout'];

    public function login()
    {
        $params   = $this->request->post();
        $username = trim($params['username'] ?? '');
        $password = trim($params['password'] ?? '');

        if (empty($username) || empty($password)) {
            return $this->fail('用户名和密码不能为空');
        }

        $admin = Admin::where('username', $username)->findOrEmpty();
        if ($admin->isEmpty()) return $this->fail('账号不存在');
        if ($admin->disable)   return $this->fail('账号已被禁用');

        if (md5(md5($password) . $admin->salt) !== $admin->password) {
            return $this->fail('密码错误');
        }

        return $this->data(['token' => AdminTokenService::createToken($admin->id), 'admin_id' => $admin->id]);
    }

    public function info()
    {
        $admin = Admin::with(['roles'])->findOrEmpty($this->adminId);
        if ($admin->isEmpty()) return $this->fail('管理员不存在');

        $roleNames = $admin->roles->column('name');

        return $this->data([
            'id'       => $admin->id,
            'username' => $admin->username,
            'nickname' => $admin->nickname,
            // Arco Design Pro Vue 展示与鉴权所需字段
            'name'     => $admin->nickname ?: $admin->username,
            'avatar'   => $admin->avatar,
            // 前端路由守卫用标量 role（'admin' 可访问全部 demo 路由）
            'role'     => $admin->root ? 'admin' : 'user',
            'root'     => $admin->root,
            'roles'    => $roleNames,
        ]);
    }

    public function logout()
    {
        return $this->success('退出成功');
    }
}
