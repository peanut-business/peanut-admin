<?php
declare(strict_types=1);

namespace app\adminapi\controller\setting;

use app\adminapi\controller\BaseAdminController;
use app\adminapi\logic\setting\OfficialAccountReplyLogic;
use app\adminapi\validate\setting\OfficialAccountReplyValidate;

class OfficialAccountReplyController extends BaseAdminController
{
    public function lists()
    {
        $params = $this->request->get();
        $this->validate($params, OfficialAccountReplyValidate::class . '.lists');
        return $this->data(OfficialAccountReplyLogic::lists($params));
    }

    public function detail()
    {
        $params = $this->request->get();
        $this->validate($params, OfficialAccountReplyValidate::class . '.detail');
        $result = OfficialAccountReplyLogic::detail((int)$params['id']);
        return $result === [] ? $this->fail('自动回复不存在') : $this->data($result);
    }

    public function add()
    {
        return $this->operate('add', 'add');
    }

    public function edit()
    {
        return $this->operate('edit', 'edit');
    }

    public function delete()
    {
        $params = $this->request->post();
        $this->validate($params, OfficialAccountReplyValidate::class . '.delete');
        $result = OfficialAccountReplyLogic::delete((int)$params['id']);
        return $result ? $this->success('删除成功') : $this->fail(OfficialAccountReplyLogic::getError());
    }

    public function updateStatus()
    {
        $params = $this->request->post();
        $this->validate($params, OfficialAccountReplyValidate::class . '.status');
        $result = OfficialAccountReplyLogic::updateStatus((int)$params['id'], (int)$params['status']);
        return $result ? $this->success('操作成功') : $this->fail(OfficialAccountReplyLogic::getError());
    }

    private function operate(string $scene, string $method)
    {
        $params = $this->request->post();
        $this->validate($params, OfficialAccountReplyValidate::class . '.' . $scene);
        $result = OfficialAccountReplyLogic::$method($params);
        return $result ? $this->success('操作成功') : $this->fail(OfficialAccountReplyLogic::getError());
    }
}
