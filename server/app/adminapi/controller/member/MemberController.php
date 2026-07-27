<?php
declare(strict_types=1);

namespace app\adminapi\controller\member;

use app\adminapi\controller\BaseAdminController;
use app\adminapi\logic\member\MemberLogic;
use app\adminapi\validate\member\MemberValidate;

class MemberController extends BaseAdminController
{
    public function lists()  { return $this->data(MemberLogic::lists($this->request->get())); }
    public function detail() { return $this->data(MemberLogic::detail((int)$this->request->get('id'))); }

    public function add()
    {
        $this->validate($this->request->post(), MemberValidate::class . '.add');
        $r = MemberLogic::add($this->request->post());
        return $r ? $this->success('操作成功') : $this->fail(MemberLogic::getError());
    }

    public function edit()
    {
        $this->validate($this->request->post(), MemberValidate::class . '.edit');
        $r = MemberLogic::edit($this->request->post());
        return $r ? $this->success('操作成功') : $this->fail(MemberLogic::getError());
    }

    public function updateStatus()
    {
        $r = MemberLogic::updateStatus(
            (int)$this->request->post('id'),
            (int)$this->request->post('status', 1)
        );
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
}
