<?php
declare(strict_types=1);

namespace app\Modules\Official\Member\Http\Controller;

use app\adminapi\controller\BaseAdminController;
use app\Modules\Official\Member\Service\MemberTagLogic;
use app\Modules\Official\Member\Validation\MemberTagValidate;
use app\common\service\member\MemberTenantContext;

class MemberTagController extends BaseAdminController
{
    public function lists()  { return $this->data(MemberTagLogic::lists(MemberTenantContext::member($this->request))); }

    public function add()
    {
        $this->validate($this->request->post(), MemberTagValidate::class . '.add');
        $r = MemberTagLogic::add(MemberTenantContext::member($this->request), $this->request->post());
        return $r ? $this->success('操作成功') : $this->fail(MemberTagLogic::getError());
    }

    public function edit()
    {
        $this->validate($this->request->post(), MemberTagValidate::class . '.edit');
        $r = MemberTagLogic::edit(MemberTenantContext::member($this->request), $this->request->post());
        return $r ? $this->success('操作成功') : $this->fail(MemberTagLogic::getError());
    }

    public function delete()
    {
        $r = MemberTagLogic::delete(MemberTenantContext::member($this->request), (int)$this->request->post('id'));
        return $r ? $this->success('操作成功') : $this->fail(MemberTagLogic::getError());
    }
}
