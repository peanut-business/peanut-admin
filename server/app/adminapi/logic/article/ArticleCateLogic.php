<?php
declare(strict_types=1);

namespace app\adminapi\logic\article;

use app\common\logic\BaseLogic;
use app\common\model\article\Article;
use app\common\model\article\ArticleCate;

/**
 * 文章分类 Logic
 */
class ArticleCateLogic extends BaseLogic
{
    /** 列表（含文章数） */
    public static function lists(): array
    {
        $list = ArticleCate::order(['sort' => 'desc', 'id' => 'desc'])
            ->select()
            ->toArray();
        foreach ($list as &$row) {
            $row['article_count'] = Article::where('cate_id', $row['id'])->count();
        }
        unset($row);
        return $list;
    }

    /** 下拉用：全部启用分类 */
    public static function all(): array
    {
        return ArticleCate::where('is_show', 1)
            ->field(['id', 'name'])
            ->order(['sort' => 'desc', 'id' => 'desc'])
            ->select()
            ->toArray();
    }

    public static function detail(int $id): array
    {
        return ArticleCate::findOrEmpty($id)->toArray();
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
        ArticleCate::update([
            'id'      => $params['id'],
            'name'    => $params['name'],
            'sort'    => (int) ($params['sort'] ?? 0),
            'is_show' => (int) ($params['is_show'] ?? 1),
        ]);
        return true;
    }

    public static function delete(int $id): bool
    {
        if (Article::where('cate_id', $id)->count() > 0) {
            self::setError('该分类下存在文章，无法删除');
            return false;
        }
        ArticleCate::destroy($id);
        return true;
    }

    public static function updateStatus(int $id, int $isShow): bool
    {
        ArticleCate::update(['id' => $id, 'is_show' => $isShow]);
        return true;
    }
}
