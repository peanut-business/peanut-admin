<?php
declare(strict_types=1);

namespace app\modules\official\member\controller;

use app\adminapi\controller\BaseAdminController;
use app\common\execution\CurrentExecutionContext;
use app\modules\official\member\contracts\MemberAdministration;
use app\modules\official\member\validate\MemberTagValidate;
use think\App;

class MemberTagController extends BaseAdminController
{
    public function __construct(App $app, CurrentExecutionContext $executionContext, private readonly MemberAdministration $members)
    {
        parent::__construct($app, $executionContext);
    }

    public function lists()
    {
        return $this->data($this->members->tags());
    }

    public function add()
    {
        $params = $this->request->post();
        $this->validate($params, MemberTagValidate::class . '.add');
        $this->members->createTag($params);
        return $this->success('操作成功');
    }

    public function edit()
    {
        $params = $this->request->post();
        $this->validate($params, MemberTagValidate::class . '.edit');
        $this->members->updateTag($params);
        return $this->success('操作成功');
    }

    public function delete()
    {
        $this->members->deleteTag((int)$this->request->post('id'));
        return $this->success('操作成功');
    }
}
