<?php
declare(strict_types=1);

namespace app\Modules\Official\Oauth\Http\Controller;

use think\App;
use app\common\execution\CurrentExecutionContext;

use app\adminapi\controller\BaseAdminController;
use app\Modules\Official\Oauth\Application\WebPageApplicationService;
use app\Modules\Official\Oauth\Validation\WebPageValidate;

class WebPageController extends BaseAdminController
{
    public function __construct(App $app, CurrentExecutionContext $executionContext, private readonly WebPageApplicationService $webPages)
    {
        parent::__construct($app, $executionContext);
    }

    public function getConfig()
    {
        return $this->data($this->webPages->getConfig(
            $this->tenantAdminContext(),
            (string)$this->request->domain(),
        ));
    }

    public function setConfig()
    {
        $params = $this->request->post();
        $this->validate($params, WebPageValidate::class);
        $this->webPages->setConfig($this->tenantAdminContext(), $params);
        return $this->success('操作成功');
    }
}
