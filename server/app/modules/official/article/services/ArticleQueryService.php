<?php
declare(strict_types=1);

namespace app\modules\official\article\services;

use app\modules\official\article\model\Article;
use app\modules\official\article\contracts\ArticleQueries;
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
