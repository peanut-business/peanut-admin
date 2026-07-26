<?php
declare(strict_types=1);

namespace app\adminapi\controller\dict;

use app\adminapi\controller\BaseAdminController;
use app\adminapi\logic\dict\DictTypeLogic;
use app\adminapi\validate\dict\DictTypeValidate;

class DictTypeController extends BaseAdminController
{
    public function lists()
    {
        $res = DictTypeLogic::lists($this->request->get());
        return $this->dataLists($res['lists'], $res['count'], $res['pageNo'], $res['pageSize']);
    }

    public function all()    { return $this->data(DictTypeLogic::all()); }
    public function detail() { return $this->data(DictTypeLogic::detail((int)$this->request->get('id'))); }

    public function add()
    {
        $this->validate($this->request->post(), DictTypeValidate::class . '.add');
        $r = DictTypeLogic::add($this->request->post());
        return $r ? $this->success('操作成功') : $this->fail(DictTypeLogic::getError());
    }

    public function edit()
    {
        $this->validate($this->request->post(), DictTypeValidate::class . '.edit');
        $r = DictTypeLogic::edit($this->request->post());
        return $r ? $this->success('操作成功') : $this->fail(DictTypeLogic::getError());
    }

    public function delete()
    {
        DictTypeLogic::delete((int)$this->request->post('id'));
        return $this->success('操作成功');
    }

    public function updateStatus()
    {
        DictTypeLogic::updateStatus((int)$this->request->post('id'), (int)$this->request->post('is_disable', 0));
        return $this->success('操作成功');
    }
}
