<?php
declare(strict_types=1);

namespace app\Modules\Official\Article\Application;

use app\common\application\BusinessException;
use app\common\http\PageResult;
use app\common\service\ProductAssetReferenceService;
use app\common\service\RichTextResourceService;
use app\common\support\PaginationInput;
use app\Modules\Official\Article\Contracts\PublicArticleQueries;
use app\Modules\Official\Article\Infrastructure\Persistence\ArticleTenantRepository;
use PeanutAdmin\Kernel\Context\AuthenticatedMemberContext;

final class PublicArticleService implements PublicArticleQueries
{
    public function __construct(
        private readonly ProductAssetReferenceService $assets,
        private readonly RichTextResourceService $richText,
    ) {}

    public function lists(array $params, int $memberId = 0): PageResult
    {
        $query = ArticleTenantRepository::articles()->field([
            'id', 'cid', 'title', 'desc', 'image',
            'click_virtual', 'click_actual', 'create_time', 'sort',
        ])->where('is_show', 1);

        $cid = (int)($params['cid'] ?? 0);
        if ($cid > 0) {
            $query->where('cid', $cid);
        }
        if (!empty($params['keyword'])) {
            $query->whereLike('title', '%' . $params['keyword'] . '%');
        }

        $sort = (string)($params['sort'] ?? 'default');
        if ($sort === 'new') {
            $query->order('id', 'desc');
        } elseif ($sort === 'hot') {
            $query->orderRaw('click_actual + click_virtual desc, id desc');
        } else {
            $query->order(['sort' => 'desc', 'id' => 'desc']);
        }

        $pageResult = ArticleTenantRepository::arrayPage(PaginationInput::from($params)->result($query));
        $lists = $pageResult->items;
        $articleIds = array_map('intval', array_column($lists, 'id'));
        $collectIds = $memberId > 0 && $articleIds !== []
            ? ArticleTenantRepository::collections()->where('member_id', $memberId)
                ->where('status', 1)
                ->whereIn('article_id', $articleIds)
                ->column('article_id')
            : [];
        $collectIds = array_map('intval', $collectIds);
        foreach ($lists as &$row) {
            $row['click'] = (int)$row['click_actual'] + (int)$row['click_virtual'];
            $row['image'] = $this->assets->forRead((string)($row['image'] ?? ''));
            $row['collect'] = in_array((int)$row['id'], $collectIds, true);
            unset($row['click_actual'], $row['click_virtual'], $row['sort']);
        }
        unset($row);

        return new PageResult($lists, $pageResult->total, $pageResult->page, $pageResult->pageSize);
    }

    public function categories(): array
    {
        return ArticleTenantRepository::categories()->field(['id', 'name'])
            ->where('is_show', 1)
            ->order(['sort' => 'desc', 'id' => 'desc'])
            ->select()
            ->toArray();
    }

    public function detail(int $id, int $memberId = 0): array
    {
        $article = ArticleTenantRepository::publishedDetail($id);
        if ($article === []) {
            return [];
        }
        $article['image'] = $this->assets->forRead((string)($article['image'] ?? ''));
        $article['content'] = $this->richText->forRead((string)($article['content'] ?? ''));
        $article['collect'] = $memberId > 0
            ? ArticleTenantRepository::isCollected($memberId, $id)
            : false;
        return $article;
    }

    public function add(int $articleId, int $memberId): void
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
    }

    public function cancel(int $articleId, int $memberId): void
    {
        ArticleTenantRepository::collections()->where('member_id', $memberId)
            ->where('article_id', $articleId)
            ->where('status', 1)
            ->update(['status' => 0]);
    }

    public function collectionLists(int $memberId, array $params): PageResult
    {
        $query = ArticleTenantRepository::collections()->alias('c')
            ->join('article a', 'a.tenant_id = c.tenant_id AND c.article_id = a.id')
            ->where('c.member_id', $memberId)
            ->where('c.status', 1)
            ->where('a.is_show', 1)
            ->where('a.delete_time', 'null')
            ->field('c.id,c.article_id,a.title,a.image,a.desc,a.is_show,a.click_virtual,a.click_actual,a.create_time,c.create_time as collect_time,a.sort');

        $pageResult = ArticleTenantRepository::arrayPage(
            PaginationInput::from($params)->result($query->order(['a.sort' => 'desc', 'c.id' => 'desc']))
        );
        $lists = $pageResult->items;
        foreach ($lists as &$row) {
            $row['click'] = (int)$row['click_actual'] + (int)$row['click_virtual'];
            $row['collect_time'] = empty($row['collect_time']) ? '' : date('Y-m-d H:i', (int)$row['collect_time']);
            unset($row['click_actual'], $row['click_virtual'], $row['sort']);
        }
        unset($row);

        return new PageResult($lists, $pageResult->total, $pageResult->page, $pageResult->pageSize);
    }

    public function countForMember(AuthenticatedMemberContext $context, int $memberId): int
    {
        return (int)ArticleTenantRepository::collections()->alias('c')
            ->join('article a', 'a.tenant_id = c.tenant_id AND c.article_id = a.id')
            ->where('c.member_id', $memberId)
            ->where('c.status', 1)
            ->where('a.is_show', 1)
            ->where('a.delete_time', 'null')
            ->count();
    }

    public function infoCenter(): array
    {
        $categories = ArticleTenantRepository::categories()->field(['id', 'name'])->where('is_show', 1)
            ->order(['sort' => 'desc', 'id' => 'desc'])->select()->toArray();
        $byCategory = [];
        foreach (ArticleTenantRepository::topPublishedByCategories(array_column($categories, 'id'), 10) as $article) {
            $article['click'] = (int)$article['click_actual'] + (int)$article['click_virtual'];
            $article['image'] = $this->assets->forRead((string)($article['image'] ?? ''));
            unset($article['click_actual'], $article['click_virtual'], $article['category_rank']);
            $byCategory[(int)$article['cid']][] = $article;
        }
        foreach ($categories as &$category) {
            $category['article'] = $byCategory[(int)$category['id']] ?? [];
        }
        unset($category);
        return $categories;
    }

    public function homeArticles(int $limit): array
    {
        $rows = ArticleTenantRepository::articles()->field([
            'id', 'title', 'desc', 'abstract', 'image', 'author',
            'click_actual', 'click_virtual', 'create_time',
        ])->where('is_show', 1)
            ->order('id', 'desc')
            ->limit($limit)
            ->select()
            ->toArray();
        foreach ($rows as &$row) {
            $row['click'] = (int)$row['click_actual'] + (int)$row['click_virtual'];
            $row['image'] = $this->assets->forRead((string)($row['image'] ?? ''));
            unset($row['click_actual'], $row['click_virtual']);
        }
        unset($row);
        return $rows;
    }

    public function pcDetail(int $memberId, int $articleId, string $source = 'default'): array
    {
        $detail = $this->detail($articleId, $memberId);
        if ($detail === []) {
            return [];
        }

        $lists = $this->limitArticles($source, 0, (int)$detail['cid']);
        $nowIndex = 0;
        foreach ($lists as $key => $item) {
            if ((int)$item['id'] === $articleId) {
                $nowIndex = $key;
                break;
            }
        }
        $detail['last'] = $lists[$nowIndex - 1] ?? [];
        $detail['next'] = $lists[$nowIndex + 1] ?? [];
        $detail['new'] = $this->limitArticles('new', 8, (int)$detail['cid'], $articleId);
        $detail['cate_name'] = (string)ArticleTenantRepository::categories()
            ->where('id', (int)$detail['cid'])
            ->value('name');
        return $detail;
    }

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
            $row['click'] = (int)$row['click_actual'] + (int)$row['click_virtual'];
            $row['image'] = $this->assets->forRead((string)($row['image'] ?? ''));
            unset($row['click_actual'], $row['click_virtual'], $row['sort']);
        }
        unset($row);
        return $rows;
    }
}
