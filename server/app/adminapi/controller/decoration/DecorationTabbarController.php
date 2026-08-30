<?php
declare(strict_types=1);

namespace app\adminapi\controller\decoration;

use think\App;

use app\adminapi\controller\BaseAdminController;
use app\adminapi\application\decoration\DecorationTabbarApplicationService;
use app\adminapi\validate\decoration\DecorationTabbarValidate;
use app\common\service\decoration\DecorationTenantContext;

class DecorationTabbarController extends BaseAdminController
{
    public function __construct(App $app, private readonly DecorationTabbarApplicationService $decorationTabbars)
    {
        parent::__construct($app);
    }

    public function detail()
    {
        return $this->data($this->decorationTabbars->detail(
            DecorationTenantContext::member()
        ));
    }

    public function save()
    {
        $params = $this->request->post();
        $this->validate($params, DecorationTabbarValidate::class);
        $result = $this->decorationTabbars->save(
            DecorationTenantContext::member(),
            (array)$params['style'],
            (array)$params['list']
        );
        return $result ? $this->success('保存成功') : $this->fail($this->decorationTabbars->getError());
    }
}
