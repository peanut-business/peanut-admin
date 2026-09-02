<?php
declare(strict_types=1);

namespace app\adminapi\controller\decoration;

use think\App;
use app\common\execution\CurrentExecutionContext;

use app\adminapi\controller\BaseAdminController;
use app\adminapi\application\decoration\DecorationTabbarApplicationService;
use app\adminapi\validate\decoration\DecorationTabbarValidate;

class DecorationTabbarController extends BaseAdminController
{
    public function __construct(App $app, CurrentExecutionContext $executionContext, private readonly DecorationTabbarApplicationService $decorationTabbars)
    {
        parent::__construct($app, $executionContext);
    }

    public function detail()
    {
        return $this->data($this->decorationTabbars->detail(
            $this->tenantAdminContext()
        ));
    }

    public function save()
    {
        $params = $this->request->post();
        $this->validate($params, DecorationTabbarValidate::class);
        $this->decorationTabbars->save(
            $this->tenantAdminContext(),
            (array)$params['style'],
            (array)$params['list']
        );
        return $this->success('保存成功');
    }
}
