<?php
declare(strict_types=1);

namespace app\api\controller;

use app\api\logic\ArticleLogic;

class ArticleController extends BaseApiController
{
    public array $notNeedLogin = ['lists', 'cate', 'detail'];

    /** 文章列表 */
    public function lists()
    {
        $params = [
            'cate_id'   => $this->request->get('cate_id/d', 0),
            'keyword'   => $this->request->get('keyword/s', ''),
            'page_no'   => $this->request->get('page_no/d', 1),
            'page_size' => $this->request->get('page_size/d', 15),
        ];

        $result = ArticleLogic::lists($params);
        return $this->dataLists($result['lists'], $result['count'], $result['page_no'], $result['page_size']);
    }

    /** 文章分类 */
    public function cate()
    {
        $result = ArticleLogic::cate();
        return $this->data($result);
    }

    /** 文章详情 */
    public function detail()
    {
        $id     = $this->request->get('id/d', 0);
        $result = ArticleLogic::detail($id, $this->memberId);

        if (empty($result)) {
            return $this->fail('文章不存在或已下架');
        }

        return $this->data($result);
    }

    /** 加入收藏 */
    public function addCollect()
    {
        $id = $this->request->post('id/d', 0);
        ArticleLogic::addCollect($id, $this->memberId);
        return $this->success('收藏成功');
    }

    /** 取消收藏 */
    public function cancelCollect()
    {
        $id = $this->request->post('id/d', 0);
        ArticleLogic::cancelCollect($id, $this->memberId);
        return $this->success('已取消收藏');
    }

    /** 我的收藏 */
    public function collect()
    {
        $params = [
            'page_no'   => $this->request->get('page_no/d', 1),
            'page_size' => $this->request->get('page_size/d', 15),
        ];

        $result = ArticleLogic::collectLists($this->memberId, $params);
        return $this->dataLists($result['lists'], $result['count'], $result['page_no'], $result['page_size']);
    }
}
