<?php
declare(strict_types=1);

namespace app\adminapi\logic\article;

use app\common\logic\BaseLogic;
use app\common\service\ProductAssetReferenceService;
use app\common\service\RichTextResourceService;
use app\common\service\article\ArticleTenantRepository;
use app\common\support\PaginationInput;
use PeanutAdmin\Kernel\Auth\TenantContext;

/** 文章管理 Logic。 */
class ArticleLogic extends BaseLogic
{
    private const PAGE_SIZE_DEFAULT = 25;
    private const PAGE_SIZE_MAX = 25000;

    /** 分页列表。 */
    public static function lists(TenantContext $context, array $params): array|false
    {
        self::clearError();
        try {
            if (in_array((int) ($params['export'] ?? 0), [1, 2], true)) {
                throw new \RuntimeException('该列表不支持导出');
            }

            $pageType = (int) ($params['page_type'] ?? 1);
            if ($pageType === 0) {
                $pageNo = 1;
                $pageSize = self::PAGE_SIZE_MAX;
            } else {
                $requestedPageSize = (int)($params['page_size'] ?? $params['limit'] ?? self::PAGE_SIZE_DEFAULT);
                if ($requestedPageSize <= 100) {
                    $pagination = PaginationInput::from($params, 1, self::PAGE_SIZE_DEFAULT);
                    $pageNo = $pagination->page;
                    $pageSize = $pagination->pageSize;
                } else {
                    $pageNo = max(1, (int)($params['page_no'] ?? $params['page'] ?? 1));
                    $pageSize = max(1, min(self::PAGE_SIZE_MAX, $requestedPageSize));
                }
            }

            $query = ArticleTenantRepository::articles($context)->field(self::fields());
            if (!empty($params['title'])) {
                $query->whereLike('title', '%' . $params['title'] . '%');
            }
            if (isset($params['cid']) && $params['cid'] !== '') {
                $query->where('cid', (int) $params['cid']);
            }
            if (isset($params['is_show']) && $params['is_show'] !== '') {
                $query->where('is_show', (int) $params['is_show']);
            }

            $count = (int) (clone $query)->count();
            $field = (string) ($params['field'] ?? '');
            $orderBy = strtolower((string) ($params['order_by'] ?? ''));
            if (in_array($field, ['create_time', 'id'], true)
                && in_array($orderBy, ['asc', 'desc'], true)) {
                $query->order($field, $orderBy);
            } else {
                $query->order(['sort' => 'desc', 'id' => 'desc']);
            }

            $lists = $query->page($pageNo, $pageSize)->select()->toArray();
            $cateNames = self::cateNames($context, array_column($lists, 'cid'));
            foreach ($lists as &$row) {
                $row = self::formatRow($row, $cateNames);
            }
            unset($row);

            return [
                'lists' => $lists,
                'count' => $count,
                'page_no' => $pageNo,
                'page_size' => $pageSize,
                'extend' => [],
            ];
        } catch (\Throwable $e) {
            return self::fail($e);
        }
    }

    public static function detail(TenantContext $context, int $id): array
    {
        self::clearError();
        $article = ArticleTenantRepository::articles($context)->field(self::fields())->where('id', $id)->findOrEmpty();
        if ($article->isEmpty()) {
            return [];
        }
        return self::formatRow($article->toArray(), self::cateNames($context, [(int) $article['cid']]));
    }

    public static function add(TenantContext $context, array $params): bool
    {
        self::clearError();
        self::requireCategory($context, (int) $params['cid']);
        ArticleTenantRepository::createArticle($context, self::writeData($context, $params));
        return true;
    }

    public static function edit(TenantContext $context, array $params): bool
    {
        self::clearError();
        try {
            self::requireCategory($context, (int) $params['cid']);
            $article = ArticleTenantRepository::articles($context)->where('id', (int) $params['id'])->findOrEmpty();
            if ($article->isEmpty()) {
                throw new \RuntimeException('资讯不存在');
            }
            $article->save(self::writeData($context, $params));
            return true;
        } catch (\Throwable $e) {
            return self::fail($e);
        }
    }

    public static function delete(TenantContext $context, int $id): bool
    {
        self::clearError();
        $article = ArticleTenantRepository::articles($context)->where('id', $id)->findOrEmpty();
        if ($article->isEmpty()) {
            self::setError('资讯不存在');
            return false;
        }
        $article->delete();
        return true;
    }

    public static function updateStatus(TenantContext $context, int $id, int $isShow): bool
    {
        self::clearError();
        $updated = ArticleTenantRepository::articles($context)->where('id', $id)->update(['is_show' => $isShow]);
        if ($updated !== 1) {
            self::setError('资讯不存在');
            return false;
        }
        return true;
    }

    private static function fields(): array
    {
        return [
            'id', 'cid', 'title', 'desc', 'abstract', 'image', 'author', 'content',
            'click_virtual', 'click_actual', 'is_show', 'sort',
            'create_time', 'update_time', 'delete_time',
        ];
    }

    private static function writeData(TenantContext $context, array $params): array
    {
        return [
            'cid' => (int) $params['cid'],
            'title' => (string) $params['title'],
            'desc' => (string) ($params['desc'] ?? ''),
            'abstract' => (string) ($params['abstract'] ?? ''),
            'image' => ProductAssetReferenceService::forStorage(
                (string) ($params['image'] ?? ''),
                null,
                $context,
            ),
            'author' => (string) ($params['author'] ?? ''),
            'content' => RichTextResourceService::forStorage(
                (string) ($params['content'] ?? ''),
                $context,
            ),
            'click_virtual' => (int) ($params['click_virtual'] ?? 0),
            'is_show' => (int) $params['is_show'],
            'sort' => (int) ($params['sort'] ?? 0),
        ];
    }

    /** @return array<int,string> */
    private static function cateNames(TenantContext $context, array $ids): array
    {
        $ids = array_values(array_unique(array_map('intval', $ids)));
        return $ids === [] ? [] : ArticleTenantRepository::categories($context)->whereIn('id', $ids)->column('name', 'id');
    }

    private static function requireCategory(TenantContext $context, int $id): void
    {
        if (ArticleTenantRepository::categories($context)->where('id', $id)->findOrEmpty()->isEmpty()) {
            throw new \RuntimeException('所属栏目必须存在');
        }
    }

    private static function formatRow(array $row, array $cateNames): array
    {
        foreach (['id', 'cid', 'click_virtual', 'click_actual', 'is_show', 'sort'] as $field) {
            $row[$field] = (int) ($row[$field] ?? 0);
        }
        $row['cate_name'] = (string) ($cateNames[$row['cid']] ?? '');
        $row['click'] = $row['click_actual'] + $row['click_virtual'];
        foreach (['create_time', 'update_time', 'delete_time'] as $field) {
            $row[$field] = self::formatTime($row[$field] ?? 0);
        }
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
