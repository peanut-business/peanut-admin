<?php
declare(strict_types=1);

namespace app\api\controller;

use app\api\logic\IndexLogic;
use app\api\logic\ArticleLogic;

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
        $result = IndexLogic::getIndexData();
        return $this->data($result);
    }

    /** PC 资讯中心（同 article/lists） */
    public function infoCenter()
    {
        $params = [
            'cate_id'   => $this->request->get('cate_id/d', 0),
            'page_no'   => $this->request->get('page_no/d', 1),
            'page_size' => $this->request->get('page_size/d', 15),
        ];

        $result = ArticleLogic::lists($params);
        return $this->dataLists($result['lists'], $result['count'], $result['page_no'], $result['page_size']);
    }

    /** PC 文章详情 */
    public function articleDetail()
    {
        $id     = $this->request->get('id/d', 0);
        $result = ArticleLogic::detail($id, $this->memberId);

        if (empty($result)) {
            return $this->fail('文章不存在');
        }

        return $this->data($result);
    }
}
