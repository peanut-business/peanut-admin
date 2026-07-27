<?php
declare(strict_types=1);

namespace app\api\controller;

use app\api\logic\IndexLogic;

class IndexController extends BaseApiController
{
    public array $notNeedLogin = ['index', 'config', 'policy'];

    /** 首页数据 */
    public function index()
    {
        $result = IndexLogic::getIndexData();
        return $this->data($result);
    }

    /** 全局配置 */
    public function config()
    {
        $result = IndexLogic::getConfigData();
        return $this->data($result);
    }

    /** 政策协议 */
    public function policy()
    {
        $type   = $this->request->get('type/s', 'service');
        $result = IndexLogic::getPolicyByType($type);
        return $this->data($result);
    }
}
