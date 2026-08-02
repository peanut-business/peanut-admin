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

    public function detail()
    {
        $params = $this->request->get();
        $this->validate($params, DictDataValidate::class . '.detail');
        $result = DictDataLogic::detail((int)$params['id']);
        return $result === [] ? $this->fail('字典数据不存在') : $this->data($result);
    }

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
        $params = $this->request->post();
        $this->validate($params, DictDataValidate::class . '.delete');
        $result = DictDataLogic::delete((int)$params['id']);
        return $result ? $this->success('操作成功') : $this->fail(DictDataLogic::getError());
    }

    public function updateStatus()
    {
        $params = $this->request->post();
        $this->validate($params, DictDataValidate::class . '.status');
        $result = DictDataLogic::updateStatus((int)$params['id'], (int)$params['is_disable']);
        return $result ? $this->success('操作成功') : $this->fail(DictDataLogic::getError());
    }
}
