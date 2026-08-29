<?php
declare(strict_types=1);

namespace app\Modules\Official\Oauth\Http\Controller;

use think\App;

use app\adminapi\controller\BaseAdminController;
use app\Modules\Official\Oauth\Application\MiniProgramApplicationService;
use app\Modules\Official\Oauth\Validation\MiniProgramValidate;
use app\common\service\member\MemberTenantContext;

class MiniProgramController extends BaseAdminController
{
    public function __construct(App $app, private readonly MiniProgramApplicationService $miniPrograms)
    {
        parent::__construct($app);
    }

    public function getConfig()
    {
        return $this->data($this->miniPrograms->getConfig(MemberTenantContext::member()));
    }

    public function setConfig()
    {
        $params = $this->request->post();
        $this->validate($params, MiniProgramValidate::class);
        return $this->miniPrograms->setConfig(MemberTenantContext::member(), $params)
            ? $this->success('操作成功')
            : $this->fail($this->miniPrograms->getError());
    }
}
