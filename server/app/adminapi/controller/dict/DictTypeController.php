<?php
declare(strict_types=1);

namespace app\adminapi\controller\dict;

use app\adminapi\controller\BaseAdminController;
use app\adminapi\logic\dict\DictTypeLogic;
use app\adminapi\validate\dict\DictTypeValidate;
use app\common\service\dict\DictTenantContext;

class DictTypeController extends BaseAdminController
{
    public function lists()
    {
        $res = DictTypeLogic::lists(DictTenantContext::member($this->request), $this->request->get());
        return $this->dataLists($res['lists'], $res['count'], $res['pageNo'], $res['pageSize']);
    }

    public function all()
    {
        return $this->data(DictTypeLogic::all(DictTenantContext::member($this->request)));
    }
    public function detail()
    {
        $params = $this->request->get();
        $this->validate($params, DictTypeValidate::class . '.detail');
        $result = DictTypeLogic::detail(DictTenantContext::member($this->request), (int)$params['id']);
        return $result === [] ? $this->fail('字典类型不存在') : $this->data($result);
    }

    public function add()
    {
        $this->validate($this->request->post(), DictTypeValidate::class . '.add');
        $r = DictTypeLogic::add(DictTenantContext::member($this->request), $this->request->post());
        return $r ? $this->success('操作成功') : $this->fail(DictTypeLogic::getError());
    }

    public function edit()
    {
        $this->validate($this->request->post(), DictTypeValidate::class . '.edit');
        $r = DictTypeLogic::edit(DictTenantContext::member($this->request), $this->request->post());
        return $r ? $this->success('操作成功') : $this->fail(DictTypeLogic::getError());
    }

    public function delete()
    {
        $params = $this->request->post();
        $this->validate($params, DictTypeValidate::class . '.delete');
        $result = DictTypeLogic::delete(DictTenantContext::member($this->request), (int)$params['id']);
        return $result ? $this->success('操作成功') : $this->fail(DictTypeLogic::getError());
    }

    public function updateStatus()
    {
        $params = $this->request->post();
        $this->validate($params, DictTypeValidate::class . '.status');
        $result = DictTypeLogic::updateStatus(
            DictTenantContext::member($this->request),
            (int)$params['id'],
            (int)$params['is_disable']
        );
        return $result ? $this->success('操作成功') : $this->fail(DictTypeLogic::getError());
    }
}
