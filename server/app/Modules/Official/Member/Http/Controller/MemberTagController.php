<?php
declare(strict_types=1);

namespace app\Modules\Official\Member\Http\Controller;

use app\adminapi\controller\BaseAdminController;
use app\Modules\Official\Member\Contracts\MemberAdministration;
use app\Modules\Official\Member\Validation\MemberTagValidate;
use think\App;

class MemberTagController extends BaseAdminController
{
    public function __construct(App $app, private readonly MemberAdministration $members)
    {
        parent::__construct($app);
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
