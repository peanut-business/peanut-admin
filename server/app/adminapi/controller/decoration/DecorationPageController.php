<?php
declare(strict_types=1);

namespace app\adminapi\controller\decoration;

use app\adminapi\controller\BaseAdminController;
use app\adminapi\logic\decoration\DecorationPageLogic;
use app\adminapi\validate\decoration\DecorationPageValidate;
use app\common\enum\decoration\DecorationEnum;
use app\common\service\decoration\DecorationTenantContext;

class DecorationPageController extends BaseAdminController
{
    public function mobileLists()
    {
        return $this->data(DecorationPageLogic::lists(
            DecorationTenantContext::member($this->request),
            DecorationEnum::MOBILE_TYPES
        ));
    }

    public function mobileDetail()
    {
        return $this->detail(DecorationEnum::MOBILE_TYPES);
    }

    public function mobileSave()
    {
        return $this->save(DecorationEnum::MOBILE_TYPES);
    }

    public function pcDetail()
    {
        return $this->detail([DecorationEnum::PC_HOME]);
    }

    public function pcLists()
    {
        return $this->data(DecorationPageLogic::lists(
            DecorationTenantContext::member($this->request),
            [DecorationEnum::PC_HOME]
        ));
    }

    public function pcSave()
    {
        return $this->save([DecorationEnum::PC_HOME]);
    }

    public function article()
    {
        $params = $this->request->get();
        $this->validate($params, DecorationPageValidate::class . '.article');
        return $this->data(DecorationPageLogic::articleOptions(
            DecorationTenantContext::member($this->request),
            (int)($params['limit'] ?? 20)
        ));
    }

    private function detail(array $allowedTypes)
    {
        $params = $this->request->get();
        $this->validate($params, DecorationPageValidate::class . '.detail');
        $result = DecorationPageLogic::detail(
            DecorationTenantContext::member($this->request),
            (int)$params['id'],
            $allowedTypes
        );
        return $result === false ? $this->fail(DecorationPageLogic::getError()) : $this->data($result);
    }

    private function save(array $allowedTypes)
    {
        $params = $this->request->post();
        $this->validate($params, DecorationPageValidate::class . '.save');
        $result = DecorationPageLogic::save(
            DecorationTenantContext::member($this->request),
            $params,
            $allowedTypes
        );
        return $result ? $this->success('保存成功') : $this->fail(DecorationPageLogic::getError());
    }
}
