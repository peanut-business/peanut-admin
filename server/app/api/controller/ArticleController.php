<?php
declare(strict_types=1);

namespace app\api\controller;

use app\api\logic\ArticleLogic;
use app\common\validate\ListsValidate;

class ArticleController extends BaseApiController
{
    public array $notNeedLogin = ['lists', 'cate', 'detail'];

    /** 文章列表 */
    public function lists()
    {
        $this->validate($this->request->get(), ListsValidate::class);
        $params = [
            'cid'       => $this->request->get('cid/d', 0),
            'keyword'   => $this->request->get('keyword/s', ''),
            'sort'      => $this->request->get('sort/s', 'default'),
            'page_no'   => $this->request->get('page_no/d', 1),
            'page_size' => $this->request->get('page_size/d', 15),
        ];

        $result = ArticleLogic::lists($params, $this->memberId);
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

        if ($result === []) {
            return $this->fail('文章不存在或已下架');
        }

        return $this->data($result);
    }

    /** 加入收藏 */
    public function addCollect()
    {
        $id = $this->request->post('id/d', 0);
        if (!ArticleLogic::addCollect($id, $this->memberId)) {
            return $this->fail(ArticleLogic::getError());
        }
        return $this->success('操作成功');
    }

    /** 取消收藏 */
    public function cancelCollect()
    {
        $id = $this->request->post('id/d', 0);
        ArticleLogic::cancelCollect($id, $this->memberId);
        return $this->success('操作成功');
    }

    /** 我的收藏 */
    public function collect()
    {
        $this->validate($this->request->get(), ListsValidate::class);
        $params = [
            'page_no'   => $this->request->get('page_no/d', 1),
            'page_size' => $this->request->get('page_size/d', 15),
        ];

        $result = ArticleLogic::collectLists($this->memberId, $params);
        return $this->dataLists($result['lists'], $result['count'], $result['page_no'], $result['page_size']);
    }
}
