<?php
declare(strict_types=1);

namespace app\api\controller;

use app\common\enum\decoration\DecorationEnum;
use app\common\service\decoration\DecorationReadService;
use app\common\application\BusinessException;
use app\common\execution\CurrentExecutionContext;
use think\App;

class DecorationController extends BaseApiController
{
    public function __construct(
        App $app,
        CurrentExecutionContext $executionContext,
        private readonly DecorationReadService $decoration,
    ) {
        parent::__construct($app, $executionContext);
    }


    public function mobilePage()
    {
        $type = (int)$this->request->get('type', DecorationEnum::MOBILE_HOME);
        if (!in_array($type, DecorationEnum::MOBILE_TYPES, true)) {
            throw BusinessException::invalid('DECORATION_PAGE_TYPE_INVALID', '移动端装修页面类型无效');
        }
        $context = $this->publicTenantContext('decoration.mobile-page');
        return $this->data($this->decoration->pageByType(
                $context,
                $type,
                'decoration.mobile-page'
        ));
    }

    public function tabbar()
    {
        $context = $this->publicTenantContext('decoration.config');
        return $this->data($this->decoration->tabbar(
                $context,
                true,
                'decoration.config'
        ));
    }

    public function pcPage()
    {
        $context = $this->publicTenantContext('decoration.pc-page');
        return $this->data($this->decoration->pageByType(
                $context,
                DecorationEnum::PC_HOME,
                'decoration.pc-page'
        ));
    }
}
