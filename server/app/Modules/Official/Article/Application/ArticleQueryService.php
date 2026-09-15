<?php
declare(strict_types=1);

namespace app\Modules\Official\Article\Application;

use app\Modules\Official\Article\Model\Article;
use app\Modules\Official\Article\Contracts\ArticleQueries;
use PeanutAdmin\Kernel\Auth\TenantContext;

final class ArticleQueryService implements ArticleQueries
{
    public function visible(TenantContext $context, int $articleId): bool
    {
        return !Article::where([])
            ->where(['id' => $articleId, 'is_show' => 1])
            ->findOrEmpty()
            ->isEmpty();
    }

    public function options(TenantContext $context, int $limit): array
    {
        return Article::where([])
            ->field(['id', 'title', 'image', 'abstract'])
            ->where('is_show', 1)
            ->order('id', 'desc')
            ->limit($limit)
            ->select()
            ->toArray();
    }
}
