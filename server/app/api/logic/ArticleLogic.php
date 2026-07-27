<?php
declare(strict_types=1);

namespace app\api\logic;

use app\common\logic\BaseLogic;
use app\common\model\article\Article;
use app\common\model\article\ArticleCate;
use app\common\model\article\ArticleCollect;

class ArticleLogic extends BaseLogic
{
    /** 文章列表（公开，支持分类筛选） */
    public static function lists(array $params): array
    {
        $page  = max(1, (int) ($params['page_no'] ?? 1));
        $limit = (int) ($params['page_size'] ?? 15);

        $query = Article::field(['id', 'cate_id', 'title', 'intro', 'image', 'author', 'click_num', 'create_time'])
            ->where('is_show', 1);

        if (!empty($params['cate_id'])) {
            $query->where('cate_id', (int) $params['cate_id']);
        }
        if (!empty($params['keyword'])) {
            $query->whereLike('title', '%' . $params['keyword'] . '%');
        }

        $count = $query->count();
        $lists = $query->with(['cate'])
            ->order(['sort' => 'desc', 'id' => 'desc'])
            ->page($page, $limit)
            ->select()
            ->toArray();

        foreach ($lists as &$row) {
            $row['cate_name'] = $row['cate']['name'] ?? '';
            unset($row['cate']);
        }
        unset($row);

        return ['lists' => $lists, 'count' => $count, 'page_no' => $page, 'page_size' => $limit];
    }

    /** 文章分类（公开） */
    public static function cate(): array
    {
        return ArticleCate::field(['id', 'name'])
            ->where('is_show', 1)
            ->order(['sort' => 'desc', 'id' => 'desc'])
            ->select()
            ->toArray();
    }

    /** 文章详情 */
    public static function detail(int $id, int $memberId = 0): array
    {
        $article = Article::findOrEmpty($id);
        if ($article->isEmpty() || !$article->is_show) {
            return [];
        }

        // 增加点击量
        $article->click_num += 1;
        $article->save();

        $data = $article->toArray();
        $data['collect'] = ArticleCollect::isCollected($memberId, $id);

        return $data;
    }

    /** 加入收藏 */
    public static function addCollect(int $articleId, int $memberId): void
    {
        if (ArticleCollect::where('member_id', $memberId)->where('article_id', $articleId)->count()) {
            return; // 已收藏
        }
        ArticleCollect::create(['member_id' => $memberId, 'article_id' => $articleId]);
    }

    /** 取消收藏 */
    public static function cancelCollect(int $articleId, int $memberId): void
    {
        ArticleCollect::where('member_id', $memberId)->where('article_id', $articleId)->delete();
    }

    /** 我的收藏列表 */
    public static function collectLists(int $memberId, array $params): array
    {
        $page  = max(1, (int) ($params['page_no'] ?? 1));
        $limit = (int) ($params['page_size'] ?? 15);

        $query = ArticleCollect::alias('c')
            ->join('article a', 'c.article_id = a.id')
            ->where('c.member_id', $memberId)
            ->where('a.delete_time', 'null')
            ->field('a.id, a.title, a.intro, a.image, a.author, a.click_num, a.create_time, c.create_time as collect_time');

        $count = $query->count();
        $lists = $query->order('c.id', 'desc')
            ->page($page, $limit)
            ->select()
            ->toArray();

        return ['lists' => $lists, 'count' => $count, 'page_no' => $page, 'page_size' => $limit];
    }
}
