<?php
declare(strict_types=1);

namespace app\common\service\article;

use app\Modules\Official\Article\Model\Article;
use app\Modules\Official\Article\Model\ArticleCate;
use app\Modules\Official\Article\Model\ArticleCollect;
use think\facade\Db;

final class ArticleTenantRepository
{
    public static function articles()
    {
        return Article::where([]);
    }

    public static function categories()
    {
        return ArticleCate::where([]);
    }

    public static function collections()
    {
        return ArticleCollect::where([]);
    }

    public static function createArticle(array $data): Article
    {
        unset($data['tenant_id']);
        return Article::create($data);
    }

    public static function createCategory(array $data): ArticleCate
    {
        unset($data['tenant_id']);
        return ArticleCate::create($data);
    }

    public static function createCollection(array $data): ArticleCollect
    {
        unset($data['tenant_id']);
        return ArticleCollect::create($data);
    }

    /** @return list<array<string,mixed>> */
    public static function topPublishedByCategories(array $categoryIds, int $limit): array
    {
        $categoryIds = array_values(array_unique(array_map('intval', $categoryIds)));
        if ($categoryIds === [] || $limit < 1) {
            return [];
        }

        $ranked = self::articles()->alias('a')->field([
            'a.id', 'a.cid', 'a.title', 'a.desc', 'a.abstract', 'a.image', 'a.author',
            'a.is_show', 'a.sort', 'a.create_time', 'a.update_time', 'a.delete_time',
            'a.click_virtual', 'a.click_actual',
        ])->fieldRaw(
            'ROW_NUMBER() OVER (PARTITION BY a.cid ORDER BY a.sort DESC, a.id DESC) AS category_rank'
        )->whereIn('a.cid', $categoryIds)
            ->where('a.is_show', 1)
            ->buildSql();

        return Db::table($ranked . ' ranked')
            ->where('category_rank', '<=', $limit)
            ->order(['cid' => 'asc', 'sort' => 'desc', 'id' => 'desc'])
            ->select()
            ->toArray();
    }
}
