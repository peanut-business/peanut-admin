<?php
declare(strict_types=1);

namespace app\Modules\Official\Article\Application;

use app\Modules\Official\Article\Contracts\ArticleCollectionSummary;
use app\common\service\article\ArticleTenantRepository;
use app\common\service\member\AuthenticatedMemberContext;

final class ArticleCollectionSummaryService implements ArticleCollectionSummary
{
    public function countForMember(AuthenticatedMemberContext $context, int $memberId): int
    {
        return (int)ArticleTenantRepository::collections()->alias('c')
            ->join('article a', 'c.article_id = a.id')
            ->where('c.member_id', $memberId)
            ->where('c.status', 1)
            ->where('a.is_show', 1)
            ->where('a.delete_time', 'null')
            ->count();
    }
}
