<?php
declare(strict_types=1);

namespace app\adminapi\logic\article;

use app\common\logic\BaseLogic;
use app\common\model\article\Article;

/**
 * 文章 Logic
 */
class ArticleLogic extends BaseLogic
{
    /** 分页列表 */
    public static function lists(array $params): array
    {
        $page  = max(1, (int) ($params['pageNo'] ?? 1));
        $limit = (int) ($params['pageSize'] ?? 15);

        $query = Article::withoutField('content')->with(['cate']);

        if (!empty($params['title'])) {
            $query->whereLike('title', '%' . $params['title'] . '%');
        }
        if (isset($params['cate_id']) && $params['cate_id'] !== '') {
            $query->where('cate_id', (int) $params['cate_id']);
        }
        if (isset($params['is_show']) && $params['is_show'] !== '') {
            $query->where('is_show', (int) $params['is_show']);
        }

        $count = $query->count();
        $lists = $query->order(['sort' => 'desc', 'id' => 'desc'])
            ->page($page, $limit)
            ->select()
            ->toArray();

        foreach ($lists as &$row) {
            $row['cate_name'] = $row['cate']['name'] ?? '';
            unset($row['cate']);
        }
        unset($row);

        return ['lists' => $lists, 'count' => $count, 'page' => $page, 'limit' => $limit];
    }

    public static function detail(int $id): array
    {
        return Article::findOrEmpty($id)->toArray();
    }

    public static function add(array $params): bool
    {
        Article::create([
            'cate_id'   => (int) $params['cate_id'],
            'title'     => $params['title'],
            'intro'     => $params['intro'] ?? '',
            'image'     => $params['image'] ?? '',
            'author'    => $params['author'] ?? '',
            'content'   => $params['content'] ?? '',
            'sort'      => (int) ($params['sort'] ?? 0),
            'click_num' => (int) ($params['click_num'] ?? 0),
            'is_show'   => (int) ($params['is_show'] ?? 1),
        ]);
        return true;
    }

    public static function edit(array $params): bool
    {
        Article::update([
            'id'        => $params['id'],
            'cate_id'   => (int) $params['cate_id'],
            'title'     => $params['title'],
            'intro'     => $params['intro'] ?? '',
            'image'     => $params['image'] ?? '',
            'author'    => $params['author'] ?? '',
            'content'   => $params['content'] ?? '',
            'sort'      => (int) ($params['sort'] ?? 0),
            'click_num' => (int) ($params['click_num'] ?? 0),
            'is_show'   => (int) ($params['is_show'] ?? 1),
        ]);
        return true;
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
}
