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

        return $this->data([
            'id'       => $admin->id,
            'username' => $admin->username,
            'nickname' => $admin->nickname,
            'avatar'   => $admin->avatar,
            'root'     => $admin->root,
            'roles'    => $admin->roles->column('name'),
        ]);
    }

    public function logout()
    {
        return $this->success('退出成功');
    }
}
