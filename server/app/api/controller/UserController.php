<?php
declare(strict_types=1);

namespace app\api\controller;

use think\App;
use app\common\execution\CurrentExecutionContext;

use app\api\application\UserApplicationService;
use app\common\service\article\ArticleTenantContext;
use app\common\service\member\MemberTenantContext;

class UserController extends BaseApiController
{
    public function __construct(App $app, CurrentExecutionContext $executionContext, private readonly UserApplicationService $users)
    {
        parent::__construct($app, $executionContext);
    }

    /** 用户中心 */
    public function center()
    {
        $data = $this->users->center(ArticleTenantContext::member(), $this->memberId);
        return $this->data($data);
    }

    /** 个人信息 */
    public function info()
    {
        $data = $this->users->info(MemberTenantContext::member(), $this->memberId);
        return $this->data($data);
    }

    /** 编辑用户信息 */
    public function setInfo()
    {
        $params = [
            'field' => $this->request->post('field/s', ''),
            'value' => $this->request->post('value', ''),
        ];

        $result = $this->users->setInfo(MemberTenantContext::member(), $this->memberId, $params);
        if ($result === false) {
            return $this->fail($this->users->getError());
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

        $result = $this->users->changePassword(MemberTenantContext::member(), $this->memberId, $params);
        if ($result === false) {
            return $this->fail($this->users->getError());
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

        $result = $this->users->bindMobile(MemberTenantContext::member(), $this->memberId, $params);
        if ($result === false) {
            return $this->fail($this->users->getError());
        }

        return $this->success('绑定成功');
    }
}
