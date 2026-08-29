<?php
declare(strict_types=1);

namespace app\Modules\Official\Oauth\Http\Controller;

use think\App;

use app\adminapi\controller\BaseAdminController;
use app\Modules\Official\Oauth\Application\OfficialAccountApplicationService;
use app\Modules\Official\Oauth\Validation\OfficialAccountValidate;
use app\common\service\member\MemberTenantContext;

class OfficialAccountController extends BaseAdminController
{
    public function __construct(App $app, private readonly OfficialAccountApplicationService $officialAccounts)
    {
        parent::__construct($app);
    }

    public function getConfig()
    {
        return $this->data($this->officialAccounts->getConfig(MemberTenantContext::member()));
    }

    public function setConfig()
    {
        $params = $this->request->post();
        $this->validate($params, OfficialAccountValidate::class);
        return $this->officialAccounts->setConfig(MemberTenantContext::member(), $params)
            ? $this->success('操作成功')
            : $this->fail($this->officialAccounts->getError());
    }
}
