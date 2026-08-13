<?php
declare(strict_types=1);

namespace app\adminapi\controller\member;

use app\adminapi\controller\BaseAdminController;
use app\adminapi\logic\member\MemberLogic;
use app\adminapi\validate\member\MemberValidate;
use app\common\service\member\MemberTenantContext;

class MemberController extends BaseAdminController
{
    public function lists()
    {
        $result = MemberLogic::lists(MemberTenantContext::member($this->request), $this->request->get());
        return $result === false ? $this->fail(MemberLogic::getError()) : $this->data($result);
    }

    public function detail()
    {
        $context = MemberTenantContext::member($this->request);
        $this->validateForTenant($context, $this->request->get(), 'detail');
        return $this->data(MemberLogic::detail($context, (int)$this->request->get('id')));
    }

    public function add()
    {
        $context = MemberTenantContext::member($this->request);
        $this->validateForTenant($context, $this->request->post(), 'add');
        $r = MemberLogic::add($context, $this->request->post());
        return $r ? $this->success('操作成功') : $this->fail(MemberLogic::getError());
    }

    public function edit()
    {
        $context = MemberTenantContext::member($this->request);
        $this->validateForTenant($context, $this->request->post(), 'setInfo');
        $r = MemberLogic::setUserInfo($context, $this->request->post());
        return $r ? $this->success('操作成功') : $this->fail(MemberLogic::getError());
    }

    /** Peanut 原有的整表单编辑兼容入口。 */
    public function profileEdit()
    {
        $context = MemberTenantContext::member($this->request);
        $this->validateForTenant($context, $this->request->post(), 'profileEdit');
        $r = MemberLogic::editProfile($context, $this->request->post());
        return $r ? $this->success('操作成功') : $this->fail(MemberLogic::getError());
    }

    public function updateStatus()
    {
        $params = $this->request->post();
        $context = MemberTenantContext::member($this->request);
        $this->validateForTenant($context, $params, 'status');
        $r = MemberLogic::updateStatus($context, (int)$params['id'], (int)$params['status']);
        return $r ? $this->success('操作成功') : $this->fail(MemberLogic::getError());
    }

    public function adjustBalance()
    {
        $context = MemberTenantContext::member($this->request);
        $this->validateForTenant($context, $this->request->post(), 'balance');
        $r = MemberLogic::adjustBalance(
            $context,
            (int)$this->request->post('id'),
            (float)$this->request->post('amount'),
            (string)$this->request->post('remark', ''),
            $this->adminId
        );
        return $r ? $this->success('操作成功') : $this->fail(MemberLogic::getError());
    }

    public function adjustMoney()
    {
        $params = $this->request->post();
        $context = MemberTenantContext::member($this->request);
        $this->validateForTenant($context, $params, 'adjustMoney');
        $r = MemberLogic::adjustUserMoney($context, $params, $this->adminId);
        return $r ? $this->success('操作成功') : $this->fail(MemberLogic::getError());
    }

    private function validateForTenant($context, array $data, string $scene): void
    {
        (new MemberValidate())->forTenant($context)->scene($scene)->failException(true)->check($data);
    }
}
