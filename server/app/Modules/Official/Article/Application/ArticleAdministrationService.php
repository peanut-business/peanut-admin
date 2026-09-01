<?php
declare(strict_types=1);

namespace app\Modules\Official\Article\Application;

use app\Modules\Official\Article\Contracts\ArticleAdministration;
use app\common\execution\CurrentExecutionContext;
use app\common\http\PageResult;
use app\common\service\ProductAssetReferenceService;
use app\common\service\RichTextResourceService;
use app\Modules\Official\Article\Infrastructure\Persistence\ArticleTenantRepository;
use app\common\support\PaginationInput;
use PeanutAdmin\Kernel\Persistence\TransactionManager;

/** Application use cases for Article content and categories. */
final class ArticleAdministrationService implements ArticleAdministration
{
    private const PAGE_SIZE_DEFAULT = 25;
    private const PAGE_SIZE_MAX = 25000;

    public function __construct(
        private readonly CurrentExecutionContext $executionContext,
        private readonly TransactionManager $transactions,
        private readonly ProductAssetReferenceService $assets,
        private readonly RichTextResourceService $richText,
    ) {}

    /** 分页列表。 */
    public function lists(array $params): PageResult
    {
        if (in_array((int) ($params['export'] ?? 0), [1, 2], true)) {
            throw new \RuntimeException('该列表不支持导出');
        }

        $pageType = (int) ($params['page_type'] ?? 1);
        if ($pageType === 0) {
            $pageNo = 1;
            $pageSize = self::PAGE_SIZE_MAX;
        } else {
            $pagination = PaginationInput::from($params, 1, self::PAGE_SIZE_DEFAULT);
            $pageNo = $pagination->page;
            $pageSize = $pagination->pageSize;
        }

        $query = ArticleTenantRepository::articles()->field(self::articleFields());
        if (!empty($params['title'])) {
            $query->whereLike('title', '%' . $params['title'] . '%');
        }
        if (isset($params['cid']) && $params['cid'] !== '') {
            $query->where('cid', (int) $params['cid']);
        }
        if (isset($params['is_show']) && $params['is_show'] !== '') {
            $query->where('is_show', (int) $params['is_show']);
        }

        $field = (string) ($params['field'] ?? '');
        $orderBy = strtolower((string) ($params['order_by'] ?? ''));
        if (in_array($field, ['create_time', 'id'], true)
            && in_array($orderBy, ['asc', 'desc'], true)) {
            $query->order($field, $orderBy);
        } else {
            $query->order(['sort' => 'desc', 'id' => 'desc']);
        }

        $pageResult = $pageType === 0
            ? PageResult::fromPaginator($query->paginate([
                'list_rows' => $pageSize,
                'page' => $pageNo,
                'var_page' => 'page_no',
            ]), $pageNo)
            : $pagination->result($query);
        $pageResult = ArticleTenantRepository::arrayPage($pageResult);
        $lists = $pageResult->items;
        $categoryNames = $this->categoryNames(array_column($lists, 'cid'));
        foreach ($lists as &$row) {
            $row = $this->formatArticleRow($row, $categoryNames);
        }
        unset($row);

        return new PageResult($lists, $pageResult->total, $pageResult->page, $pageResult->pageSize);
    }

    /** @return array<string,mixed> */
    public function detail(int $id): array
    {
        $article = ArticleTenantRepository::articles()
            ->field(self::articleFields())
            ->where('id', $id)
            ->findOrEmpty();
        if ($article->isEmpty()) {
            return [];
        }

        return $this->formatArticleRow(
            $article->toArray(),
            $this->categoryNames([(int) $article['cid']]),
        );
    }

    public function add(array $params): void
    {
        $this->requireCategory((int) $params['cid']);
        ArticleTenantRepository::createArticle($this->articleWriteData($params));
    }

    public function edit(array $params): void
    {
        $this->requireCategory((int) $params['cid']);
        $article = ArticleTenantRepository::articles()
            ->where('id', (int) $params['id'])
            ->findOrEmpty();
        if ($article->isEmpty()) {
            throw new \RuntimeException('资讯不存在');
        }

        $article->save($this->articleWriteData($params));
    }

    public function delete(int $id): void
    {
        $article = ArticleTenantRepository::articles()->where('id', $id)->findOrEmpty();
        if ($article->isEmpty()) {
            throw new \RuntimeException('资讯不存在');
        }

        $article->delete();
    }

    public function updateStatus(int $id, int $isShow): void
    {
        $updated = ArticleTenantRepository::articles()
            ->where('id', $id)
            ->update(['is_show' => $isShow]);
        if ($updated !== 1) {
            throw new \RuntimeException('资讯不存在');
        }
    }

    /** 分页列表（含文章数）。 */
    public function categoryLists(array $params): PageResult
    {
        if (in_array((int) ($params['export'] ?? 0), [1, 2], true)) {
            throw new \RuntimeException('该列表不支持导出');
        }

        $pageType = (int) ($params['page_type'] ?? 1);
        if ($pageType === 0) {
            $pageNo = 1;
            $pageSize = self::PAGE_SIZE_MAX;
        } else {
            $pagination = PaginationInput::from($params, 1, self::PAGE_SIZE_DEFAULT);
            $pageNo = $pagination->page;
            $pageSize = $pagination->pageSize;
        }

        $query = ArticleTenantRepository::categories()->field([
            'id', 'name', 'sort', 'is_show', 'create_time', 'update_time', 'delete_time',
        ]);
        $field = (string) ($params['field'] ?? '');
        $orderBy = strtolower((string) ($params['order_by'] ?? ''));
        if (in_array($field, ['create_time', 'id'], true)
            && in_array($orderBy, ['asc', 'desc'], true)) {
            $query->order($field, $orderBy);
        } else {
            $query->order(['sort' => 'desc', 'id' => 'desc']);
        }

        $pageResult = $pageType === 0
            ? PageResult::fromPaginator($query->paginate([
                'list_rows' => $pageSize,
                'page' => $pageNo,
                'var_page' => 'page_no',
            ]), $pageNo)
            : $pagination->result($query);
        $pageResult = ArticleTenantRepository::arrayPage($pageResult);
        $lists = $pageResult->items;
        $articleCounts = $this->articleCounts(array_column($lists, 'id'));
        foreach ($lists as &$row) {
            $row = $this->formatCategoryRow($row);
            $row['article_count'] = $articleCounts[(int) $row['id']] ?? 0;
        }
        unset($row);

        return new PageResult($lists, $pageResult->total, $pageResult->page, $pageResult->pageSize);
    }

    /** 下拉用：全部启用分类。 */
    public function allCategories(): array
    {
        $lists = ArticleTenantRepository::categories()
            ->where('is_show', 1)
            ->field(['id', 'name', 'sort', 'is_show', 'create_time', 'update_time', 'delete_time'])
            ->order(['sort' => 'desc', 'id' => 'desc'])
            ->select()
            ->toArray();

        return array_map(fn(array $row): array => $this->formatCategoryRow($row), $lists);
    }

    /** @return array<string,mixed> */
    public function categoryDetail(int $id): array
    {
        $category = ArticleTenantRepository::categories()->field([
            'id', 'name', 'sort', 'is_show', 'create_time', 'update_time', 'delete_time',
        ])->where('id', $id)->findOrEmpty();

        return $category->isEmpty() ? [] : $this->formatCategoryRow($category->toArray());
    }

    public function addCategory(array $params): void
    {
        ArticleTenantRepository::createCategory([
            'name' => $params['name'],
            'sort' => (int) ($params['sort'] ?? 0),
            'is_show' => (int) ($params['is_show'] ?? 1),
        ]);
    }

    public function editCategory(array $params): void
    {
        $category = ArticleTenantRepository::categories()
            ->where('id', (int) $params['id'])
            ->findOrEmpty();
        if ($category->isEmpty()) {
            throw new \RuntimeException('资讯分类不存在');
        }

        $category->save([
            'name' => $params['name'],
            'sort' => (int) ($params['sort'] ?? 0),
            'is_show' => (int) $params['is_show'],
        ]);
    }

    public function deleteCategory(int $id): void
    {
        $this->transactions->run(function () use ($id): void {
            $category = ArticleTenantRepository::categories()->where('id', $id)->lock(true)->findOrEmpty();
            if ($category->isEmpty()) {
                throw new \RuntimeException('资讯分类不存在');
            }

            if (!ArticleTenantRepository::articles()->where('cid', $id)->lock(true)->findOrEmpty()->isEmpty()) {
                throw new \RuntimeException('资讯分类已使用，请先删除绑定该资讯分类的资讯');
            }

            $category->delete();
        });
    }

    public function updateCategoryStatus(int $id, int $isShow): void
    {
        $updated = ArticleTenantRepository::categories()
            ->where('id', $id)
            ->update(['is_show' => $isShow]);
        if ($updated !== 1) {
            throw new \RuntimeException('资讯分类不存在');
        }
    }

    /** @return list<string> */
    private static function articleFields(): array
    {
        return [
            'id', 'cid', 'title', 'desc', 'abstract', 'image', 'author', 'content',
            'click_virtual', 'click_actual', 'is_show', 'sort',
            'create_time', 'update_time', 'delete_time',
        ];
    }

    /** @return array<string,mixed> */
    private function articleWriteData(array $params): array
    {
        $context = $this->executionContext->tenantAdmin();
        return [
            'cid' => (int) $params['cid'],
            'title' => (string) $params['title'],
            'desc' => (string) ($params['desc'] ?? ''),
            'abstract' => (string) ($params['abstract'] ?? ''),
            'image' => $this->assets->forStorage(
                (string) ($params['image'] ?? ''),
                null,
                $context,
            ),
            'author' => (string) ($params['author'] ?? ''),
            'content' => $this->richText->forStorage(
                (string) ($params['content'] ?? ''),
                $context,
            ),
            'click_virtual' => (int) ($params['click_virtual'] ?? 0),
            'is_show' => (int) $params['is_show'],
            'sort' => (int) ($params['sort'] ?? 0),
        ];
    }

    /** @return array<int,string> */
    private function categoryNames(array $ids): array
    {
        $ids = array_values(array_unique(array_map('intval', $ids)));
        return $ids === []
            ? []
            : ArticleTenantRepository::categories()->whereIn('id', $ids)->column('name', 'id');
    }

    private function requireCategory(int $id): void
    {
        if (ArticleTenantRepository::categories()->where('id', $id)->findOrEmpty()->isEmpty()) {
            throw new \RuntimeException('所属栏目必须存在');
        }
    }

    /** @param array<string,mixed> $row @param array<int,string> $categoryNames */
    private function formatArticleRow(array $row, array $categoryNames): array
    {
        foreach (['id', 'cid', 'click_virtual', 'click_actual', 'is_show', 'sort'] as $field) {
            $row[$field] = (int) ($row[$field] ?? 0);
        }
        $row['cate_name'] = (string) ($categoryNames[$row['cid']] ?? '');
        $row['click'] = $row['click_actual'] + $row['click_virtual'];
        $row['image'] = $this->assets->forRead((string) ($row['image'] ?? ''));
        $row['content'] = $this->richText->forRead((string) ($row['content'] ?? ''));
        foreach (['create_time', 'update_time', 'delete_time'] as $field) {
            $row[$field] = self::formatTime($row[$field] ?? 0);
        }
        return $row;
    }

    /** @return array<int,int> */
    private function articleCounts(array $categoryIds): array
    {
        if ($categoryIds === []) {
            return [];
        }

        $rows = ArticleTenantRepository::articles()
            ->whereIn('cid', $categoryIds)
            ->field('cid, COUNT(*) AS article_count')
            ->group('cid')
            ->select()
            ->toArray();
        $counts = [];
        foreach ($rows as $row) {
            $counts[(int) $row['cid']] = (int) $row['article_count'];
        }
        return $counts;
    }

    /** @param array<string,mixed> $row */
    private function formatCategoryRow(array $row): array
    {
        $row['id'] = (int) $row['id'];
        $row['sort'] = (int) $row['sort'];
        $row['is_show'] = (int) $row['is_show'];
        $row['create_time'] = self::formatTime($row['create_time'] ?? 0);
        $row['update_time'] = self::formatTime($row['update_time'] ?? 0);
        $row['delete_time'] = self::formatTime($row['delete_time'] ?? 0);
        return $row;
    }

    private static function formatTime(mixed $value): string
    {
        if (empty($value)) {
            return '';
        }
        return is_numeric($value) ? date('Y-m-d H:i:s', (int) $value) : (string) $value;
    }
}
