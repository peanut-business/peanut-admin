<?php
declare(strict_types=1);

namespace app\api\logic;

use app\common\logic\BaseLogic;
use app\common\model\article\Article;
use app\common\model\article\ArticleCollect;
use app\common\service\article\ArticleTenantRepository;
use PeanutAdmin\Kernel\Auth\TenantContext;
use PeanutAdmin\Kernel\Context\TenantSystemContext;

class ArticleLogic extends BaseLogic
{
    /** 公开文章列表。 */
    public static function lists(TenantContext|TenantSystemContext $context, array $params, int $memberId = 0): array
    {
        $page = max(1, (int) ($params['page_no'] ?? 1));
        $limit = max(1, (int) ($params['page_size'] ?? 15));
        $query = ArticleTenantRepository::articles($context)->field([
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

        $count = (int) (clone $query)->count();
        $sort = (string) ($params['sort'] ?? 'default');
        if ($sort === 'new') {
            $query->order('id', 'desc');
        } elseif ($sort === 'hot') {
            $query->orderRaw('click_actual + click_virtual desc, id desc');
        } else {
            $query->order(['sort' => 'desc', 'id' => 'desc']);
        }

        $lists = $query->page($page, $limit)->select()->toArray();
        $articleIds = array_map('intval', array_column($lists, 'id'));
        $collectIds = $memberId > 0 && $articleIds !== []
            ? ArticleTenantRepository::collections($context)->where('member_id', $memberId)
                ->where('status', 1)
                ->whereIn('article_id', $articleIds)
                ->column('article_id')
            : [];
        foreach ($lists as &$row) {
            $row['click'] = (int) $row['click_actual'] + (int) $row['click_virtual'];
            $row['collect'] = in_array((int) $row['id'], array_map('intval', $collectIds), true);
            unset($row['click_actual'], $row['click_virtual'], $row['sort']);
        }
        unset($row);

        return ['lists' => $lists, 'count' => $count, 'page_no' => $page, 'page_size' => $limit];
    }

    /** 文章分类（公开）。 */
    public static function cate(TenantContext|TenantSystemContext $context): array
    {
        return ArticleTenantRepository::categories($context)->field(['id', 'name'])
            ->where('is_show', 1)
            ->order(['sort' => 'desc', 'id' => 'desc'])
            ->select()
            ->toArray();
    }

    /** 文章详情。 */
    public static function detail(TenantContext|TenantSystemContext $context, int $id, int $memberId = 0): array
    {
        $article = Article::getArticleDetailArr($context, $id);
        if ($article === []) {
            return [];
        }
        $article['collect'] = $context instanceof TenantContext
            ? ArticleCollect::isCollected($context, $memberId, $id)
            : false;
        return $article;
    }

    public static function addCollect(TenantContext $context, int $articleId, int $memberId): bool
    {
        try {
            $article = ArticleTenantRepository::articles($context)->where('id', $articleId)
                ->where('is_show', 1)
                ->findOrEmpty();
            if ($article->isEmpty()) {
                throw new \RuntimeException('文章不存在或已下架');
            }

            $collect = ArticleTenantRepository::collections($context)->where('member_id', $memberId)
                ->where('article_id', $articleId)
                ->findOrEmpty();
            if ($collect->isEmpty()) {
                ArticleTenantRepository::createCollection($context, [
                    'member_id' => $memberId,
                    'article_id' => $articleId,
                    'status' => 1,
                ]);
            } else {
                $collect->status = 1;
                $collect->save();
            }
            return true;
        } catch (\Throwable $e) {
            self::setError($e->getMessage());
            return false;
        }
    }

    public static function cancelCollect(TenantContext $context, int $articleId, int $memberId): void
    {
        ArticleTenantRepository::collections($context)->where('member_id', $memberId)
            ->where('article_id', $articleId)
            ->where('status', 1)
            ->update(['status' => 0]);
    }

    /** 我的收藏列表，仅返回仍发布的文章。 */
    public static function collectLists(TenantContext $context, int $memberId, array $params): array
    {
        $page = max(1, (int) ($params['page_no'] ?? 1));
        $limit = max(1, (int) ($params['page_size'] ?? 15));
        $tenantId = $context->tenantId;
        $query = ArticleCollect::alias('c')
            ->join('article a', 'c.tenant_id = a.tenant_id AND c.article_id = a.id')
            ->where('c.tenant_id', $tenantId)
            ->where('a.tenant_id', $tenantId)
            ->where('c.member_id', $memberId)
            ->where('c.status', 1)
            ->where('a.is_show', 1)
            ->where('a.delete_time', 'null')
            ->field('c.id,c.article_id,a.title,a.image,a.desc,a.is_show,a.click_virtual,a.click_actual,a.create_time,c.create_time as collect_time,a.sort');

        $count = (int) (clone $query)->count();
        $lists = $query->order(['a.sort' => 'desc', 'c.id' => 'desc'])
            ->page($page, $limit)->select()->toArray();
        foreach ($lists as &$row) {
            $row['click'] = (int) $row['click_actual'] + (int) $row['click_virtual'];
            $row['collect_time'] = empty($row['collect_time']) ? '' : date('Y-m-d H:i', (int) $row['collect_time']);
            unset($row['click_actual'], $row['click_virtual'], $row['sort']);
        }
        unset($row);

        return ['lists' => $lists, 'count' => $count, 'page_no' => $page, 'page_size' => $limit];
    }

    /** PC 资讯中心：启用分类内最多十篇文章。 */
    public static function infoCenter(TenantContext|TenantSystemContext $context): array
    {
        $categories = ArticleTenantRepository::categories($context)->field(['id', 'name'])->where('is_show', 1)
            ->order(['sort' => 'desc', 'id' => 'desc'])->select()->toArray();
        foreach ($categories as &$category) {
            $articles = ArticleTenantRepository::articles($context)->field([
                'id', 'cid', 'title', 'desc', 'abstract', 'image', 'author',
                'is_show', 'sort', 'create_time', 'update_time', 'delete_time',
                'click_virtual', 'click_actual',
            ])->where('cid', (int) $category['id'])
                ->where('is_show', 1)
                ->order(['sort' => 'desc', 'id' => 'desc'])->limit(10)->select()->toArray();
            foreach ($articles as &$article) {
                $article['click'] = (int) $article['click_actual'] + (int) $article['click_virtual'];
                unset($article['click_actual'], $article['click_virtual']);
            }
            unset($article);
            $category['article'] = $articles;
        }
        unset($category);
        return $categories;
    }

    /** PC 文章详情，含前后篇与同分类最新资讯。 */
    public static function pcDetail(TenantContext|TenantSystemContext $context, int $memberId, int $articleId, string $source = 'default'): array
    {
        $detail = self::detail($context, $articleId, $memberId);
        if ($detail === []) {
            return [];
        }

        $lists = self::limitArticles($context, $source, 0, (int) $detail['cid']);
        $nowIndex = 0;
        foreach ($lists as $key => $item) {
            if ((int) $item['id'] === $articleId) {
                $nowIndex = $key;
                break;
            }
        }
        $detail['last'] = $lists[$nowIndex - 1] ?? [];
        $detail['next'] = $lists[$nowIndex + 1] ?? [];
        $detail['new'] = self::limitArticles($context, 'new', 8, (int) $detail['cid'], $articleId);
        $detail['cate_name'] = (string) ArticleTenantRepository::categories($context)->where('id', (int) $detail['cid'])->value('name');
        return $detail;
    }

    /** PC/首页聚合用文章集。 */
    public static function limitArticles(TenantContext|TenantSystemContext $context, string $sortType, int $limit = 0, int $cid = 0, int $excludeId = 0): array
    {
        $query = ArticleTenantRepository::articles($context)->field([
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
            unset($row['click_actual'], $row['click_virtual'], $row['sort']);
        }
        unset($row);
        return $rows;
    }
}
