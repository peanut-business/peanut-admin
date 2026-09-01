<?php
declare(strict_types=1);

namespace app\Modules\Official\Task\Http\Controller;

use think\App;

use app\adminapi\controller\BaseAdminController;
use app\common\execution\CurrentExecutionContext;
use app\Modules\Official\Task\Application\CrontabApplicationService;
use app\Modules\Official\Task\Validation\CrontabValidate;

/**
 * 定时任务控制器
 */
class CrontabController extends BaseAdminController
{
    public function __construct(
        App $app,
        CurrentExecutionContext $executionContext,
        private readonly CrontabApplicationService $crontabs,
    )
    {
        parent::__construct($app, $executionContext);
    }

    public function lists()
    {
        $this->tenantAdminContext();
        $res = $this->crontabs->lists($this->request->get());
        return $this->data($res);
    }

    public function detail()
    {
        $params = $this->request->get();
        $this->validate($params, CrontabValidate::class . '.detail');
        $this->tenantAdminContext();
        $result = $this->crontabs->detail((int)$params['id']);
        return $this->data($result);
    }

    public function add()
    {
        $this->validate($this->request->post(), CrontabValidate::class . '.add');
        $this->tenantAdminContext();
        $this->crontabs->add($this->request->post());
        return $this->success('添加成功');
    }

    public function edit()
    {
        $this->validate($this->request->post(), CrontabValidate::class . '.edit');
        $this->tenantAdminContext();
        $this->crontabs->edit($this->request->post());
        return $this->success('编辑成功');
    }

    public function delete()
    {
        $params = $this->request->post();
        $this->validate($params, CrontabValidate::class . '.delete');
        $this->tenantAdminContext();
        $this->crontabs->delete((int)$params['id']);
        return $this->success('删除成功');
    }

    public function operate()
    {
        $params = $this->request->post();
        $this->validate($params, CrontabValidate::class . '.operate');
        $id      = (int)$params['id'];
        $operate = (string)$params['operate'];
        $this->tenantAdminContext();
        $this->crontabs->operate($id, $operate);
        return $this->success('操作成功');
    }

    public function expression()
    {
        $params = $this->request->get();
        $this->validate($params, CrontabValidate::class . '.expression');
        $expression = (string)$params['expression'];
        return $this->data($this->crontabs->expression($expression));
    }
}
