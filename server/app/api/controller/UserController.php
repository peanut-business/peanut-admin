<?php
declare(strict_types=1);

namespace app\api\controller;

use app\api\logic\UserLogic;

class UserController extends BaseApiController
{
    /** 用户中心 */
    public function center()
    {
        $data = UserLogic::center($this->memberId);
        return $this->data($data);
    }

    /** 个人信息 */
    public function info()
    {
        $data = UserLogic::info($this->memberId);
        return $this->data($data);
    }

    /** 编辑用户信息 */
    public function setInfo()
    {
        $params = [
            'field' => $this->request->post('field/s', ''),
            'value' => $this->request->post('value', ''),
        ];

        $result = UserLogic::setInfo($this->memberId, $params);
        if ($result === false) {
            return $this->fail(UserLogic::getError());
        }

        return $this->success('修改成功');
    }

    /** 修改密码 */
    public function changePassword()
    {
        $params = [
            'old_password' => $this->request->post('old_password/s', ''),
            'password'     => $this->request->post('password/s', ''),
        ];

        if (empty($params['old_password']) || empty($params['password'])) {
            return $this->fail('旧密码和新密码不能为空');
        }

        $result = UserLogic::changePassword($this->memberId, $params);
        if ($result === false) {
            return $this->fail(UserLogic::getError());
        }

        return $this->success('修改成功');
    }

    /** 绑定/变更手机号 */
    public function bindMobile()
    {
        $params = [
            'mobile' => $this->request->post('mobile/s', ''),
            'code'   => $this->request->post('code/s', ''),
        ];

        $result = UserLogic::bindMobile($this->memberId, $params);
        if ($result === false) {
            return $this->fail(UserLogic::getError());
        }

        return $this->success('绑定成功');
    }
}
