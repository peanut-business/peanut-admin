<?php
declare(strict_types=1);

namespace app\api\controller;

use app\common\enum\decoration\DecorationEnum;
use app\common\service\decoration\DecorationReadService;
use app\common\application\BusinessException;

class DecorationController extends BaseApiController
{
    public array $notNeedLogin = ['mobilePage', 'tabbar', 'pcPage'];

    public function mobilePage()
    {
        $type = (int)$this->request->get('type', DecorationEnum::MOBILE_HOME);
        if (!in_array($type, DecorationEnum::MOBILE_TYPES, true)) {
            throw BusinessException::invalid('DECORATION_PAGE_TYPE_INVALID', '移动端装修页面类型无效');
        }
        $context = $this->publicTenantContext('decoration.mobile-page');
        return $this->data(DecorationReadService::pageByType(
                $context,
                $type,
                'decoration.mobile-page'
        ));
    }

    public function tabbar()
    {
        $context = $this->publicTenantContext('decoration.config');
        return $this->data(DecorationReadService::tabbar(
                $context,
                true,
                'decoration.config'
        ));
    }

    public function pcPage()
    {
        $context = $this->publicTenantContext('decoration.pc-page');
        return $this->data(DecorationReadService::pageByType(
                $context,
                DecorationEnum::PC_HOME,
                'decoration.pc-page'
        ));
    }
}
