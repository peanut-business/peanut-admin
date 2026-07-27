<?php
declare(strict_types=1);

namespace app\adminapi\controller\member;

use app\adminapi\controller\BaseAdminController;
use app\adminapi\logic\member\MemberTagLogic;
use app\adminapi\validate\member\MemberTagValidate;

class MemberTagController extends BaseAdminController
{
    public function lists()  { return $this->data(MemberTagLogic::lists()); }

    public function add()
    {
        $this->validate($this->request->post(), MemberTagValidate::class . '.add');
        $r = MemberTagLogic::add($this->request->post());
        return $r ? $this->success('操作成功') : $this->fail(MemberTagLogic::getError());
    }

    public function edit()
    {
        $this->validate($this->request->post(), MemberTagValidate::class . '.edit');
        $r = MemberTagLogic::edit($this->request->post());
        return $r ? $this->success('操作成功') : $this->fail(MemberTagLogic::getError());
    }

    public function delete()
    {
        $r = MemberTagLogic::delete((int)$this->request->post('id'));
        return $r ? $this->success('操作成功') : $this->fail(MemberTagLogic::getError());
    }
}
