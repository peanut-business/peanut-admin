<?php
declare(strict_types=1);

namespace app\api\controller;

use app\common\enum\decoration\DecorationEnum;
use app\common\service\decoration\DecorationReadService;
use app\common\service\decoration\DecorationTenantContext;

class DecorationController extends BaseApiController
{
    public array $notNeedLogin = ['mobilePage', 'tabbar', 'pcPage'];

    public function mobilePage()
    {
        $type = (int)$this->request->get('type', DecorationEnum::MOBILE_HOME);
        if (!in_array($type, DecorationEnum::MOBILE_TYPES, true)) {
            return $this->fail('移动端装修页面类型无效');
        }
        try {
            $context = DecorationTenantContext::read(
                $this->request,
                DecorationTenantContext::MOBILE_PAGE_OPERATION
            );
            return $this->data(DecorationReadService::pageByType(
                $context,
                $type,
                DecorationTenantContext::MOBILE_PAGE_OPERATION
            ));
        } catch (\Throwable $e) {
            return $this->fail($e->getMessage());
        }
    }

    public function tabbar()
    {
        try {
            $context = DecorationTenantContext::read(
                $this->request,
                DecorationTenantContext::CONFIG_OPERATION
            );
            return $this->data(DecorationReadService::tabbar(
                $context,
                true,
                DecorationTenantContext::CONFIG_OPERATION
            ));
        } catch (\Throwable $e) {
            return $this->fail($e->getMessage());
        }
    }

    public function pcPage()
    {
        try {
            $context = DecorationTenantContext::read(
                $this->request,
                DecorationTenantContext::PC_PAGE_OPERATION
            );
            return $this->data(DecorationReadService::pageByType(
                $context,
                DecorationEnum::PC_HOME,
                DecorationTenantContext::PC_PAGE_OPERATION
            ));
        } catch (\Throwable $e) {
            return $this->fail($e->getMessage());
        }
    }
}
