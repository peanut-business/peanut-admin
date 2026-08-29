<?php
declare(strict_types=1);

namespace app\api\controller;

use think\App;

use app\api\application\ArticleApplicationService;
use app\common\validate\ListsValidate;
use app\common\service\article\ArticleTenantContext;

class ArticleController extends BaseApiController
{
    public function __construct(App $app, private readonly ArticleApplicationService $articles)
    {
        parent::__construct($app);
    }

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

        ArticleTenantContext::read('article.lists');
        $result = $this->articles->lists($params, $this->memberId);
        return $this->data($result);
    }

    /** 文章分类 */
    public function cate()
    {
        ArticleTenantContext::read('article.cate');
        $result = $this->articles->cate();
        return $this->data($result);
    }

    /** 文章详情 */
    public function detail()
    {
        $id     = $this->request->get('id/d', 0);
        ArticleTenantContext::read('article.detail');
        $result = $this->articles->detail($id, $this->memberId);

        if ($result === []) {
            return $this->fail('文章不存在或已下架');
        }

        return $this->data($result);
    }

    /** 加入收藏 */
    public function addCollect()
    {
        $id = $this->request->post('id/d', 0);
        ArticleTenantContext::member();
        if (!$this->articles->addCollect($id, $this->memberId)) {
            return $this->fail($this->articles->getError());
        }
        return $this->success('操作成功');
    }

    /** 取消收藏 */
    public function cancelCollect()
    {
        $id = $this->request->post('id/d', 0);
        ArticleTenantContext::member();
        $this->articles->cancelCollect($id, $this->memberId);
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

        ArticleTenantContext::member();
        $result = $this->articles->collectLists($this->memberId, $params);
        return $this->data($result);
    }
}
