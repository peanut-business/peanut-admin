<?php
declare(strict_types=1);

namespace app\adminapi\controller\notice;

use app\adminapi\controller\BaseAdminController;
use app\adminapi\logic\notice\NoticeTemplateLogic;
use app\adminapi\validate\notice\NoticeTemplateValidate;

class NoticeTemplateController extends BaseAdminController
{
    public function lists()
    {
        return $this->data(NoticeTemplateLogic::lists($this->request->get()));
    }

    public function add()
    {
        $this->validate($this->request->post(), NoticeTemplateValidate::class . '.add');
        $r = NoticeTemplateLogic::add($this->request->post());
        return $r ? $this->success('操作成功') : $this->fail(NoticeTemplateLogic::getError());
    }

    public function edit()
    {
        $this->validate($this->request->post(), NoticeTemplateValidate::class . '.edit');
        $r = NoticeTemplateLogic::edit($this->request->post());
        return $r ? $this->success('操作成功') : $this->fail(NoticeTemplateLogic::getError());
    }

    public function delete()
    {
        $ids = (array) $this->request->post('ids', []);
        $r   = NoticeTemplateLogic::delete($ids);
        return $r ? $this->success('操作成功') : $this->fail(NoticeTemplateLogic::getError());
    }
}
