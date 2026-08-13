<?php
declare(strict_types=1);

namespace app\adminapi\controller\decoration;

use app\adminapi\controller\BaseAdminController;
use app\adminapi\logic\decoration\DecorationTabbarLogic;
use app\adminapi\validate\decoration\DecorationTabbarValidate;
use app\common\service\decoration\DecorationTenantContext;

class DecorationTabbarController extends BaseAdminController
{
    public function detail()
    {
        return $this->data(DecorationTabbarLogic::detail(
            DecorationTenantContext::member($this->request)
        ));
    }

    public function save()
    {
        $params = $this->request->post();
        $this->validate($params, DecorationTabbarValidate::class);
        $result = DecorationTabbarLogic::save(
            DecorationTenantContext::member($this->request),
            (array)$params['style'],
            (array)$params['list']
        );
        return $result ? $this->success('保存成功') : $this->fail(DecorationTabbarLogic::getError());
    }
}
