<?php
declare(strict_types=1);

namespace app\api\controller;

use app\api\logic\ArticleLogic;
use app\api\logic\IndexLogic;
use app\api\logic\PcLogic;
use app\common\service\article\ArticleTenantContext;

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
        $result = PcLogic::getIndexData(ArticleTenantContext::read($this->request, 'article.pc-index'));
        return $this->data($result);
    }

    /** PC 资讯中心（同 article/lists） */
    public function infoCenter()
    {
        return $this->data(ArticleLogic::infoCenter(ArticleTenantContext::read($this->request, 'article.info-center')));
    }

    /** PC 文章详情 */
    public function articleDetail()
    {
        $id     = $this->request->get('id/d', 0);
        $source = $this->request->get('source/s', 'default');
        $context = ArticleTenantContext::read($this->request, 'article.pc-detail');
        return $this->data(ArticleLogic::pcDetail($context, $this->memberId, $id, $source));
    }
}
