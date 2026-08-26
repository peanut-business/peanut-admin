<?php
declare(strict_types=1);

namespace app\Modules\Official\Article\Service;

use app\common\logic\BaseLogic;
use app\common\service\article\ArticleTenantRepository;
use app\common\support\PaginationInput;
use PeanutAdmin\Kernel\Auth\TenantContext;
use think\facade\Db;

/**
 * 文章分类 Logic
 */
class ArticleCateLogic extends BaseLogic
{
    private const PAGE_SIZE_DEFAULT = 25;
    private const PAGE_SIZE_MAX = 25000;

    /** 分页列表（含文章数） */
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

            $query = ArticleTenantRepository::categories($context)->field([
                'id', 'name', 'sort', 'is_show', 'create_time', 'update_time', 'delete_time',
            ]);
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
            $articleCounts = self::articleCounts($context, array_column($lists, 'id'));
            foreach ($lists as &$row) {
                $row = self::formatRow($row);
                $row['article_count'] = $articleCounts[(int) $row['id']] ?? 0;
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

    /** 下拉用：全部启用分类 */
    public static function all(TenantContext $context): array
    {
        self::clearError();
        $lists = ArticleTenantRepository::categories($context)->where('is_show', 1)
            ->field(['id', 'name', 'sort', 'is_show', 'create_time', 'update_time', 'delete_time'])
            ->order(['sort' => 'desc', 'id' => 'desc'])
            ->select()
            ->toArray();

        return array_map([self::class, 'formatRow'], $lists);
    }

    public static function detail(TenantContext $context, int $id): array
    {
        self::clearError();
        $articleCate = ArticleTenantRepository::categories($context)->field([
            'id', 'name', 'sort', 'is_show', 'create_time', 'update_time', 'delete_time',
        ])->where('id', $id)->findOrEmpty();
        return $articleCate->isEmpty() ? [] : self::formatRow($articleCate->toArray());
    }

    public static function add(TenantContext $context, array $params): bool
    {
        self::clearError();
        ArticleTenantRepository::createCategory($context, [
            'name'    => $params['name'],
            'sort'    => (int) ($params['sort'] ?? 0),
            'is_show' => (int) ($params['is_show'] ?? 1),
        ]);
        return true;
    }

    public static function edit(TenantContext $context, array $params): bool
    {
        self::clearError();
        try {
            $data = [
                'name'    => $params['name'],
                'sort'    => (int) ($params['sort'] ?? 0),
                'is_show' => (int) $params['is_show'],
            ];
            $category = ArticleTenantRepository::categories($context)->where('id', (int) $params['id'])->findOrEmpty();
            if ($category->isEmpty()) {
                throw new \RuntimeException('资讯分类不存在');
            }
            $category->save($data);
            return true;
        } catch (\Throwable $e) {
            return self::fail($e);
        }
    }

    public static function delete(TenantContext $context, int $id): bool
    {
        self::clearError();
        Db::startTrans();
        try {
            $articleCate = ArticleTenantRepository::categories($context)->where('id', $id)->lock(true)->findOrEmpty();
            if ($articleCate->isEmpty()) {
                throw new \RuntimeException('资讯分类不存在');
            }

            if (!ArticleTenantRepository::articles($context)->where('cid', $id)->lock(true)->findOrEmpty()->isEmpty()) {
                throw new \RuntimeException('资讯分类已使用，请先删除绑定该资讯分类的资讯');
            }

            $articleCate->delete();
            Db::commit();
            return true;
        } catch (\Throwable $e) {
            Db::rollback();
            return self::fail($e);
        }
    }

    public static function updateStatus(TenantContext $context, int $id, int $isShow): bool
    {
        self::clearError();
        $updated = ArticleTenantRepository::categories($context)->where('id', $id)->update(['is_show' => $isShow]);
        if ($updated !== 1) {
            self::setError('资讯分类不存在');
            return false;
        }
        return true;
    }

    /** @return array<int,int> */
    private static function articleCounts(TenantContext $context, array $categoryIds): array
    {
        if ($categoryIds === []) {
            return [];
        }

        $rows = ArticleTenantRepository::articles($context)->whereIn('cid', $categoryIds)
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

    private static function formatRow(array $row): array
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
