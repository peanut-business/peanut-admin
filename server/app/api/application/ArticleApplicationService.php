<?php
declare(strict_types=1);

namespace app\api\application;

use app\common\http\PageResult;
use app\common\application\BusinessException;
use app\common\service\ProductAssetReferenceService;
use app\Modules\Official\Article\Model\Article;
use app\Modules\Official\Article\Model\ArticleCollect;
use app\Modules\Official\Article\Infrastructure\Persistence\ArticleTenantRepository;
use app\common\execution\CurrentExecutionContext;
use app\common\support\PaginationInput;

class ArticleApplicationService
{
    /** 公开文章列表。 */
    public function lists(array $params, int $memberId = 0): PageResult
    {
        $query = ArticleTenantRepository::articles()->field([
            'id', 'cid', 'title', 'desc', 'image',
            'click_virtual', 'click_actual', 'create_time', 'sort',
        ])->where('is_show', 1);

        $cid = (int) ($params['cid'] ?? 0);
        if ($cid > 0) {
            $query->where('cid', $cid);
        }
        if (!empty($params['keyword'])) {
            $query->whereLike('title', '%' . $params['keyword'] . '%');
        }

        $sort = (string) ($params['sort'] ?? 'default');
        if ($sort === 'new') {
            $query->order('id', 'desc');
        } elseif ($sort === 'hot') {
            $query->orderRaw('click_actual + click_virtual desc, id desc');
        } else {
            $query->order(['sort' => 'desc', 'id' => 'desc']);
        }

        $pageResult = PaginationInput::from($params)->result($query);
        $lists = array_map(static fn($item): array => $item instanceof \think\Model ? $item->toArray() : (array) $item, $pageResult->items);
        $articleIds = array_map('intval', array_column($lists, 'id'));
        $collectIds = $memberId > 0 && $articleIds !== []
            ? ArticleTenantRepository::collections()->where('member_id', $memberId)
                ->where('status', 1)
                ->whereIn('article_id', $articleIds)
                ->column('article_id')
            : [];
        foreach ($lists as &$row) {
            $row['click'] = (int) $row['click_actual'] + (int) $row['click_virtual'];
            $row['image'] = ProductAssetReferenceService::forRead((string)($row['image'] ?? ''));
            $row['collect'] = in_array((int) $row['id'], array_map('intval', $collectIds), true);
            unset($row['click_actual'], $row['click_virtual'], $row['sort']);
        }
        unset($row);

        return new PageResult($lists, $pageResult->total, $pageResult->page, $pageResult->pageSize);
    }

    /** 文章分类（公开）。 */
    public function cate(): array
    {
        return ArticleTenantRepository::categories()->field(['id', 'name'])
            ->where('is_show', 1)
            ->order(['sort' => 'desc', 'id' => 'desc'])
            ->select()
            ->toArray();
    }

    /** 文章详情。 */
    public function detail(int $id, int $memberId = 0): array
    {
        $article = Article::getArticleDetailArr($id);
        if ($article === []) {
            return [];
        }
        $article['collect'] = $memberId > 0
            ? ArticleCollect::isCollected($memberId, $id)
            : false;
        return $article;
    }

    public function addCollect(int $articleId, int $memberId): bool
    {
        $article = ArticleTenantRepository::articles()->where('id', $articleId)
                ->where('is_show', 1)
                ->findOrEmpty();
            if ($article->isEmpty()) {
                throw BusinessException::notFound('ARTICLE_NOT_FOUND', '文章不存在或已下架');
            }

            $collect = ArticleTenantRepository::collections()->where('member_id', $memberId)
                ->where('article_id', $articleId)
                ->findOrEmpty();
            if ($collect->isEmpty()) {
                ArticleTenantRepository::createCollection([
                    'member_id' => $memberId,
                    'article_id' => $articleId,
                    'status' => 1,
                ]);
            } else {
                $collect->status = 1;
                $collect->save();
            }
        return true;
    }

    public function cancelCollect(int $articleId, int $memberId): void
    {
        ArticleTenantRepository::collections()->where('member_id', $memberId)
            ->where('article_id', $articleId)
            ->where('status', 1)
            ->update(['status' => 0]);
    }

    /** 我的收藏列表，仅返回仍发布的文章。 */
    public function collectLists(int $memberId, array $params): PageResult
    {
        $query = ArticleTenantRepository::collections()->alias('c')
            ->join('article a', 'c.article_id = a.id')
            ->where('c.member_id', $memberId)
            ->where('c.status', 1)
            ->where('a.is_show', 1)
            ->where('a.delete_time', 'null')
            ->field('c.id,c.article_id,a.title,a.image,a.desc,a.is_show,a.click_virtual,a.click_actual,a.create_time,c.create_time as collect_time,a.sort');

        $pageResult = PaginationInput::from($params)->result($query->order(['a.sort' => 'desc', 'c.id' => 'desc']));
        $lists = array_map(static fn($item): array => $item instanceof \think\Model ? $item->toArray() : (array) $item, $pageResult->items);
        foreach ($lists as &$row) {
            $row['click'] = (int) $row['click_actual'] + (int) $row['click_virtual'];
            $row['collect_time'] = empty($row['collect_time']) ? '' : date('Y-m-d H:i', (int) $row['collect_time']);
            unset($row['click_actual'], $row['click_virtual'], $row['sort']);
        }
        unset($row);

        return new PageResult($lists, $pageResult->total, $pageResult->page, $pageResult->pageSize);
    }

    /** PC 资讯中心：启用分类内最多十篇文章。 */
    public function infoCenter(): array
    {
        $categories = ArticleTenantRepository::categories()->field(['id', 'name'])->where('is_show', 1)
            ->order(['sort' => 'desc', 'id' => 'desc'])->select()->toArray();
        $byCategory = [];
        foreach (ArticleTenantRepository::topPublishedByCategories(array_column($categories, 'id'), 10) as $article) {
            $article['click'] = (int) $article['click_actual'] + (int) $article['click_virtual'];
            $article['image'] = ProductAssetReferenceService::forRead((string)($article['image'] ?? ''));
            unset($article['click_actual'], $article['click_virtual'], $article['category_rank']);
            $byCategory[(int)$article['cid']][] = $article;
        }
        foreach ($categories as &$category) {
            $category['article'] = $byCategory[(int)$category['id']] ?? [];
        }
        unset($category);
        return $categories;
    }

    /** PC 文章详情，含前后篇与同分类最新资讯。 */
    public function pcDetail(int $memberId, int $articleId, string $source = 'default'): array
    {
        $detail = self::detail($articleId, $memberId);
        if ($detail === []) {
            return [];
        }

        $lists = self::limitArticles($source, 0, (int) $detail['cid']);
        $nowIndex = 0;
        foreach ($lists as $key => $item) {
            if ((int) $item['id'] === $articleId) {
                $nowIndex = $key;
                break;
            }
        }
        $detail['last'] = $lists[$nowIndex - 1] ?? [];
        $detail['next'] = $lists[$nowIndex + 1] ?? [];
        $detail['new'] = self::limitArticles('new', 8, (int) $detail['cid'], $articleId);
        $detail['cate_name'] = (string) ArticleTenantRepository::categories()->where('id', (int) $detail['cid'])->value('name');
        return $detail;
    }

    /** PC/首页聚合用文章集。 */
    public function limitArticles(string $sortType, int $limit = 0, int $cid = 0, int $excludeId = 0): array
    {
        $query = ArticleTenantRepository::articles()->field([
            'id', 'cid', 'title', 'desc', 'abstract', 'image', 'author',
            'click_actual', 'click_virtual', 'create_time', 'sort',
        ])->where('is_show', 1);
        if ($cid > 0) {
            $query->where('cid', $cid);
        }
        if ($excludeId > 0) {
            $query->where('id', '<>', $excludeId);
        }
        if ($sortType === 'new') {
            $query->order('id', 'desc');
        } elseif ($sortType === 'hot') {
            $query->orderRaw('click_actual + click_virtual desc, id desc');
        } else {
            $query->order(['sort' => 'desc', 'id' => 'desc']);
        }
        if ($limit > 0) {
            $query->limit($limit);
        }
        $rows = $query->select()->toArray();
        foreach ($rows as &$row) {
            $row['click'] = (int) $row['click_actual'] + (int) $row['click_virtual'];
            $row['image'] = ProductAssetReferenceService::forRead((string)($row['image'] ?? ''));
            unset($row['click_actual'], $row['click_virtual'], $row['sort']);
        }
        unset($row);
        return $rows;
    }
}
