<?php
declare(strict_types=1);

namespace app\adminapi\logic\article;

use app\common\logic\BaseLogic;
use app\common\model\article\Article;
use app\common\model\article\ArticleCate;

/** 文章管理 Logic。 */
class ArticleLogic extends BaseLogic
{
    private const PAGE_SIZE_DEFAULT = 25;
    private const PAGE_SIZE_MAX = 25000;

    /** 分页列表。 */
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

            $query = Article::field(self::fields());
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
            $cateNames = self::cateNames(array_column($lists, 'cid'));
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
            self::setError($e->getMessage());
            return false;
        }
    }

    public static function detail(int $id): array
    {
        $article = Article::field(self::fields())->findOrEmpty($id);
        if ($article->isEmpty()) {
            return [];
        }
        return self::formatRow($article->toArray(), self::cateNames([(int) $article['cid']]));
    }

    public static function add(array $params): bool
    {
        Article::create(self::writeData($params));
        return true;
    }

    public static function edit(array $params): bool
    {
        try {
            Article::update(['id' => (int) $params['id']] + self::writeData($params));
            return true;
        } catch (\Throwable $e) {
            self::setError($e->getMessage());
            return false;
        }
    }

    public static function delete(int $id): bool
    {
        Article::destroy($id);
        return true;
    }

    public static function updateStatus(int $id, int $isShow): bool
    {
        Article::update(['id' => $id, 'is_show' => $isShow]);
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

    private static function writeData(array $params): array
    {
        return [
            'cid' => (int) $params['cid'],
            'title' => (string) $params['title'],
            'desc' => (string) ($params['desc'] ?? ''),
            'abstract' => (string) ($params['abstract'] ?? ''),
            'image' => (string) ($params['image'] ?? ''),
            'author' => (string) ($params['author'] ?? ''),
            'content' => (string) ($params['content'] ?? ''),
            'click_virtual' => (int) ($params['click_virtual'] ?? 0),
            'is_show' => (int) $params['is_show'],
            'sort' => (int) ($params['sort'] ?? 0),
        ];
    }

    /** @return array<int,string> */
    private static function cateNames(array $ids): array
    {
        $ids = array_values(array_unique(array_map('intval', $ids)));
        return $ids === [] ? [] : ArticleCate::whereIn('id', $ids)->column('name', 'id');
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
