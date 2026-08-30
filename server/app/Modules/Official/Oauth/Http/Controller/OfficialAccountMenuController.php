<?php
declare(strict_types=1);

namespace app\Modules\Official\Oauth\Http\Controller;

use think\App;

use app\adminapi\controller\BaseAdminController;
use app\Modules\Official\Oauth\Application\OfficialAccountMenuApplicationService;
use app\Modules\Official\Oauth\Validation\OfficialAccountMenuValidate;
use app\common\service\member\MemberTenantContext;

class OfficialAccountMenuController extends BaseAdminController
{
    public function __construct(App $app, private readonly OfficialAccountMenuApplicationService $officialAccountMenus)
    {
        parent::__construct($app);
    }

    public function detail()
    {
        return $this->data($this->officialAccountMenus->detail(MemberTenantContext::member()));
    }

    public function save()
    {
        $params = $this->request->post();
        $this->validate($params, OfficialAccountMenuValidate::class);
        $result = $this->officialAccountMenus->save(
            MemberTenantContext::member(),
            (array)$params['menu']
        );
        return $result ? $this->success('保存成功') : $this->fail($this->officialAccountMenus->getError());
    }

    public function saveAndPublish()
    {
        $params = $this->request->post();
        $this->validate($params, OfficialAccountMenuValidate::class);
        $result = $this->officialAccountMenus->saveAndPublish(
            MemberTenantContext::member(),
            (array)$params['menu']
        );
        return $result ? $this->success('发布成功') : $this->fail($this->officialAccountMenus->getError());
    }
}
