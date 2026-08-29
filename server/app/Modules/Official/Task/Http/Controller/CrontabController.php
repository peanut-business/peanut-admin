<?php
declare(strict_types=1);

namespace app\Modules\Official\Task\Http\Controller;

use think\App;

use app\adminapi\controller\BaseAdminController;
use app\Modules\Official\Task\Application\CrontabApplicationService;
use app\Modules\Official\Task\Validation\CrontabValidate;
use app\common\service\crontab\CrontabTenantContext;

/**
 * 定时任务控制器
 */
class CrontabController extends BaseAdminController
{
    public function __construct(App $app, private readonly CrontabApplicationService $crontabs)
    {
        parent::__construct($app);
    }

    public function lists()
    {
        CrontabTenantContext::member();
        $res = $this->crontabs->lists($this->request->get());
        return $this->data($res);
    }

    public function detail()
    {
        $params = $this->request->get();
        $this->validate($params, CrontabValidate::class . '.detail');
        CrontabTenantContext::member();
        $result = $this->crontabs->detail((int)$params['id']);
        return $result === [] ? $this->fail('定时任务不存在') : $this->data($result);
    }

    public function add()
    {
        $this->validate($this->request->post(), CrontabValidate::class . '.add');
        CrontabTenantContext::member();
        $r = $this->crontabs->add($this->request->post());
        return $r ? $this->success('添加成功') : $this->fail($this->crontabs->getError());
    }

    public function edit()
    {
        $this->validate($this->request->post(), CrontabValidate::class . '.edit');
        CrontabTenantContext::member();
        $r = $this->crontabs->edit($this->request->post());
        return $r ? $this->success('编辑成功') : $this->fail($this->crontabs->getError());
    }

    public function delete()
    {
        $params = $this->request->post();
        $this->validate($params, CrontabValidate::class . '.delete');
        CrontabTenantContext::member();
        $result = $this->crontabs->delete((int)$params['id']);
        return $result ? $this->success('删除成功') : $this->fail($this->crontabs->getError());
    }

    public function operate()
    {
        $params = $this->request->post();
        $this->validate($params, CrontabValidate::class . '.operate');
        $id      = (int)$params['id'];
        $operate = (string)$params['operate'];
        CrontabTenantContext::member();
        $r = $this->crontabs->operate($id, $operate);
        return $r ? $this->success('操作成功') : $this->fail($this->crontabs->getError());
    }

    public function expression()
    {
        $params = $this->request->get();
        $this->validate($params, CrontabValidate::class . '.expression');
        $expression = (string)$params['expression'];
        return $this->data($this->crontabs->expression($expression));
    }
}
