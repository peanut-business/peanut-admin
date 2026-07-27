<?php
declare(strict_types=1);

namespace app\adminapi\controller\dict;

use app\adminapi\controller\BaseAdminController;
use app\adminapi\logic\dict\DictDataLogic;
use app\adminapi\validate\dict\DictDataValidate;

class DictDataController extends BaseAdminController
{
    public function lists()
    {
        $res = DictDataLogic::lists($this->request->get());
        return $this->dataLists($res['lists'], $res['count'], $res['pageNo'], $res['pageSize']);
    }

    /** 按类型标识取启用数据项（业务前端用） */
    public function byType()
    {
        return $this->data(DictDataLogic::byType((string)$this->request->get('type_value', '')));
    }

    public function detail() { return $this->data(DictDataLogic::detail((int)$this->request->get('id'))); }

    public function add()
    {
        $this->validate($this->request->post(), DictDataValidate::class . '.add');
        $r = DictDataLogic::add($this->request->post());
        return $r ? $this->success('操作成功') : $this->fail(DictDataLogic::getError());
    }

    public function edit()
    {
        $this->validate($this->request->post(), DictDataValidate::class . '.edit');
        $r = DictDataLogic::edit($this->request->post());
        return $r ? $this->success('操作成功') : $this->fail(DictDataLogic::getError());
    }

    public function delete()
    {
        DictDataLogic::delete((int)$this->request->post('id'));
        return $this->success('操作成功');
    }

    public function updateStatus()
    {
        DictDataLogic::updateStatus((int)$this->request->post('id'), (int)$this->request->post('is_disable', 0));
        return $this->success('操作成功');
    }
}
