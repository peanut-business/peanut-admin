<?php
declare(strict_types=1);

namespace app\modules\official\oauth\controller;

use think\App;
use app\common\execution\CurrentExecutionContext;

use app\adminapi\controller\BaseAdminController;
use app\modules\official\oauth\services\OfficialAccountMenuApplicationService;
use app\modules\official\oauth\validate\OfficialAccountMenuValidate;

class OfficialAccountMenuController extends BaseAdminController
{
    public function __construct(App $app, CurrentExecutionContext $executionContext, private readonly OfficialAccountMenuApplicationService $officialAccountMenus)
    {
        parent::__construct($app, $executionContext);
    }

    public function detail()
    {
        return $this->data($this->officialAccountMenus->detail($this->tenantAdminContext()));
    }

    public function save()
    {
        $params = $this->request->post();
        $this->validate($params, OfficialAccountMenuValidate::class);
        $this->officialAccountMenus->save(
            $this->tenantAdminContext(),
            (array)$params['menu']
        );
        return $this->success('保存成功');
    }

    public function saveAndPublish()
    {
        $params = $this->request->post();
        $this->validate($params, OfficialAccountMenuValidate::class);
        $this->officialAccountMenus->saveAndPublish(
            $this->tenantAdminContext(),
            (array)$params['menu']
        );
        return $this->success('发布成功');
    }
}
