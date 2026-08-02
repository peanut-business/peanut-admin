<?php
declare(strict_types=1);

namespace app\adminapi\controller\member;

use app\adminapi\controller\BaseAdminController;
use app\adminapi\logic\member\MemberLogic;
use app\adminapi\validate\member\MemberValidate;

class MemberController extends BaseAdminController
{
    public function lists()
    {
        $result = MemberLogic::lists($this->request->get());
        return $result === false ? $this->fail(MemberLogic::getError()) : $this->data($result);
    }

    public function detail()
    {
        $this->validate($this->request->get(), MemberValidate::class . '.detail');
        return $this->data(MemberLogic::detail((int)$this->request->get('id')));
    }

    public function add()
    {
        $this->validate($this->request->post(), MemberValidate::class . '.add');
        $r = MemberLogic::add($this->request->post());
        return $r ? $this->success('操作成功') : $this->fail(MemberLogic::getError());
    }

    public function edit()
    {
        $this->validate($this->request->post(), MemberValidate::class . '.setInfo');
        $r = MemberLogic::setUserInfo($this->request->post());
        return $r ? $this->success('操作成功') : $this->fail(MemberLogic::getError());
    }

    /** Peanut 原有的整表单编辑兼容入口。 */
    public function profileEdit()
    {
        $this->validate($this->request->post(), MemberValidate::class . '.profileEdit');
        $r = MemberLogic::editProfile($this->request->post());
        return $r ? $this->success('操作成功') : $this->fail(MemberLogic::getError());
    }

    public function updateStatus()
    {
        $params = $this->request->post();
        $this->validate($params, MemberValidate::class . '.status');
        $r = MemberLogic::updateStatus((int)$params['id'], (int)$params['status']);
        return $r ? $this->success('操作成功') : $this->fail(MemberLogic::getError());
    }

    public function adjustBalance()
    {
        $this->validate($this->request->post(), MemberValidate::class . '.balance');
        $r = MemberLogic::adjustBalance(
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
        $this->validate($params, MemberValidate::class . '.adjustMoney');
        $r = MemberLogic::adjustUserMoney($params, $this->adminId);
        return $r ? $this->success('操作成功') : $this->fail(MemberLogic::getError());
    }
}
