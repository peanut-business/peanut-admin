<?php
declare(strict_types=1);

namespace app\adminapi\logic\article;

use app\common\logic\BaseLogic;
use app\common\model\article\Article;
use app\common\model\article\ArticleCate;
use think\facade\Db;

/**
 * 文章分类 Logic
 */
class ArticleCateLogic extends BaseLogic
{
    private const PAGE_SIZE_DEFAULT = 25;
    private const PAGE_SIZE_MAX = 25000;

    /** 分页列表（含文章数） */
    public static function lists(array $params): array|false
    {
        try {
            if (in_array((int) ($params['export'] ?? 0), [1, 2], true)) {
                throw new \RuntimeException('该列表不支持导出');
            }

            $pageType = (int) ($params['page_type'] ?? 1);
            $pageNo = $pageType === 0 ? 1 : max(1, (int) ($params['page_no'] ?? 1));
            $pageSize = $pageType === 0
                ? self::PAGE_SIZE_MAX
                : max(1, min(self::PAGE_SIZE_MAX, (int) ($params['page_size'] ?? self::PAGE_SIZE_DEFAULT)));

            $query = ArticleCate::field([
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
            $articleCounts = self::articleCounts(array_column($lists, 'id'));
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
            self::setError($e->getMessage());
            return false;
        }
    }

    /** 下拉用：全部启用分类 */
    public static function all(): array
    {
        $lists = ArticleCate::where('is_show', 1)
            ->field(['id', 'name', 'sort', 'is_show', 'create_time', 'update_time', 'delete_time'])
            ->order(['sort' => 'desc', 'id' => 'desc'])
            ->select()
            ->toArray();

        return array_map([self::class, 'formatRow'], $lists);
    }

    public static function detail(int $id): array
    {
        $articleCate = ArticleCate::field([
            'id', 'name', 'sort', 'is_show', 'create_time', 'update_time', 'delete_time',
        ])->findOrEmpty($id);
        return $articleCate->isEmpty() ? [] : self::formatRow($articleCate->toArray());
    }

    public static function add(array $params): bool
    {
        ArticleCate::create([
            'name'    => $params['name'],
            'sort'    => (int) ($params['sort'] ?? 0),
            'is_show' => (int) ($params['is_show'] ?? 1),
        ]);
        return true;
    }

    public static function edit(array $params): bool
    {
        try {
            $data = [
                'id'      => (int) $params['id'],
                'name'    => $params['name'],
                'sort'    => (int) ($params['sort'] ?? 0),
                'is_show' => (int) $params['is_show'],
            ];
            ArticleCate::update($data);
            return true;
        } catch (\Throwable $e) {
            self::setError($e->getMessage());
            return false;
        }
    }

    public static function delete(int $id): bool
    {
        Db::startTrans();
        try {
            $articleCate = ArticleCate::where('id', $id)->lock(true)->findOrEmpty();
            if ($articleCate->isEmpty()) {
                throw new \RuntimeException('资讯分类不存在');
            }

            if (!Article::where('cid', $id)->lock(true)->findOrEmpty()->isEmpty()) {
                throw new \RuntimeException('资讯分类已使用，请先删除绑定该资讯分类的资讯');
            }

            $articleCate->delete();
            Db::commit();
            return true;
        } catch (\Throwable $e) {
            Db::rollback();
            self::setError($e->getMessage());
            return false;
        }
    }

    public static function updateStatus(int $id, int $isShow): bool
    {
        ArticleCate::update(['id' => $id, 'is_show' => $isShow]);
        return true;
    }

    /** @return array<int,int> */
    private static function articleCounts(array $categoryIds): array
    {
        if ($categoryIds === []) {
            return [];
        }

        $rows = Article::whereIn('cid', $categoryIds)
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
