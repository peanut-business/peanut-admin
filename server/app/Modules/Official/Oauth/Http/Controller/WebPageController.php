<?php
declare(strict_types=1);

namespace app\Modules\Official\Oauth\Http\Controller;

use think\App;

use app\adminapi\controller\BaseAdminController;
use app\Modules\Official\Oauth\Application\WebPageApplicationService;
use app\Modules\Official\Oauth\Validation\WebPageValidate;
use app\common\service\member\MemberTenantContext;

class WebPageController extends BaseAdminController
{
    public function __construct(App $app, private readonly WebPageApplicationService $webPages)
    {
        parent::__construct($app);
    }

    public function getConfig()
    {
        return $this->data($this->webPages->getConfig(MemberTenantContext::member()));
    }

    public function setConfig()
    {
        $params = $this->request->post();
        $this->validate($params, WebPageValidate::class);
        $this->webPages->setConfig(MemberTenantContext::member(), $params);
        return $this->success('操作成功');
    }
}
