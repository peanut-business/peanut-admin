<?php
declare(strict_types=1);

namespace app\api\controller;

use app\api\logic\ArticleLogic;
use app\api\logic\IndexLogic;
use app\api\logic\PcLogic;

/**
 * PC 端聚合接口（部分端点返回更丰富的字段或不同格式）
 */
class PcController extends BaseApiController
{
    public array $notNeedLogin = ['config', 'index', 'infoCenter', 'articleDetail'];

    /** PC 配置 */
    public function config()
    {
        $result = IndexLogic::getConfigData();
        return $this->data($result);
    }

    /** PC 首页 */
    public function index()
    {
        $result = PcLogic::getIndexData();
        return $this->data($result);
    }

    /** PC 资讯中心（同 article/lists） */
    public function infoCenter()
    {
        return $this->data(ArticleLogic::infoCenter());
    }

    /** PC 文章详情 */
    public function articleDetail()
    {
        $id     = $this->request->get('id/d', 0);
        $source = $this->request->get('source/s', 'default');
        return $this->data(ArticleLogic::pcDetail($this->memberId, $id, $source));
    }
}
