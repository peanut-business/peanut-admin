<?php
declare(strict_types=1);

namespace app\api\controller;

use app\common\enum\decoration\DecorationEnum;
use app\common\service\decoration\DecorationReadService;

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
            return $this->data(DecorationReadService::pageByType($type));
        } catch (\Throwable $e) {
            return $this->fail($e->getMessage());
        }
    }

    public function tabbar()
    {
        return $this->data(DecorationReadService::tabbar(true));
    }

    public function pcPage()
    {
        try {
            return $this->data(DecorationReadService::pageByType(DecorationEnum::PC_HOME));
        } catch (\Throwable $e) {
            return $this->fail($e->getMessage());
        }
    }
}
