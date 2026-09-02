<?php
declare(strict_types=1);

namespace app\Modules\Official\Article\Application;

use app\Modules\Official\Article\Contracts\ArticleQueries;
use app\Modules\Official\Article\Infrastructure\Persistence\ArticleTenantRepository;
use PeanutAdmin\Kernel\Auth\TenantContext;

final class ArticleQueryService implements ArticleQueries
{
    public function visible(TenantContext $context, int $articleId): bool
    {
        return !ArticleTenantRepository::articles()
            ->where(['id' => $articleId, 'is_show' => 1])
            ->findOrEmpty()
            ->isEmpty();
    }

    public function options(TenantContext $context, int $limit): array
    {
        return ArticleTenantRepository::articles()
            ->field(['id', 'title', 'image', 'abstract'])
            ->where('is_show', 1)
            ->order('id', 'desc')
            ->limit($limit)
            ->select()
            ->toArray();
    }
}
