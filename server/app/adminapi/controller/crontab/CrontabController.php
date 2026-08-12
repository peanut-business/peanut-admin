<?php
declare(strict_types=1);

namespace app\adminapi\controller\crontab;

use app\adminapi\controller\BaseAdminController;
use app\adminapi\logic\crontab\CrontabLogic;
use app\adminapi\validate\crontab\CrontabValidate;
use app\common\service\crontab\CrontabTenantContext;

/**
 * 定时任务控制器
 */
class CrontabController extends BaseAdminController
{
    public function lists()
    {
        $res = CrontabLogic::lists(CrontabTenantContext::member($this->request), $this->request->get());
        return $this->dataLists($res['lists'], $res['count'], $res['pageNo'], $res['pageSize']);
    }

    public function detail()
    {
        $params = $this->request->get();
        $this->validate($params, CrontabValidate::class . '.detail');
        $result = CrontabLogic::detail(CrontabTenantContext::member($this->request), (int)$params['id']);
        return $result === [] ? $this->fail('定时任务不存在') : $this->data($result);
    }

    public function add()
    {
        $this->validate($this->request->post(), CrontabValidate::class . '.add');
        $r = CrontabLogic::add(CrontabTenantContext::member($this->request), $this->request->post());
        return $r ? $this->success('添加成功') : $this->fail(CrontabLogic::getError());
    }

    public function edit()
    {
        $this->validate($this->request->post(), CrontabValidate::class . '.edit');
        $r = CrontabLogic::edit(CrontabTenantContext::member($this->request), $this->request->post());
        return $r ? $this->success('编辑成功') : $this->fail(CrontabLogic::getError());
    }

    public function delete()
    {
        $params = $this->request->post();
        $this->validate($params, CrontabValidate::class . '.delete');
        $result = CrontabLogic::delete(CrontabTenantContext::member($this->request), (int)$params['id']);
        return $result ? $this->success('删除成功') : $this->fail(CrontabLogic::getError());
    }

    public function operate()
    {
        $params = $this->request->post();
        $this->validate($params, CrontabValidate::class . '.operate');
        $id      = (int)$params['id'];
        $operate = (string)$params['operate'];
        $r = CrontabLogic::operate(CrontabTenantContext::member($this->request), $id, $operate);
        return $r ? $this->success('操作成功') : $this->fail(CrontabLogic::getError());
    }

    public function expression()
    {
        $params = $this->request->get();
        $this->validate($params, CrontabValidate::class . '.expression');
        $expression = (string)$params['expression'];
        return $this->data(CrontabLogic::expression($expression));
    }
}
