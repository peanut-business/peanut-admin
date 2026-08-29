<?php
declare(strict_types=1);

namespace app\Modules\Official\Oauth\Http\Controller;

use think\App;

use app\adminapi\controller\BaseAdminController;
use app\Modules\Official\Oauth\Application\OpenPlatformApplicationService;
use app\Modules\Official\Oauth\Validation\OpenPlatformValidate;
use app\common\service\member\MemberTenantContext;

class OpenPlatformController extends BaseAdminController
{
    public function __construct(App $app, private readonly OpenPlatformApplicationService $openPlatforms)
    {
        parent::__construct($app);
    }

    public function getConfig()
    {
        return $this->data($this->openPlatforms->getConfig(MemberTenantContext::member()));
    }

    public function setConfig()
    {
        $params = $this->request->post();
        $this->validate($params, OpenPlatformValidate::class);
        return $this->openPlatforms->setConfig(MemberTenantContext::member(), $params)
            ? $this->success('操作成功')
            : $this->fail($this->openPlatforms->getError());
    }
}
