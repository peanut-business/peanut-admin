<?php
declare(strict_types=1);

namespace app\Modules\Official\Article\Contracts;

use app\common\service\member\AuthenticatedMemberContext;

interface ArticleCollectionSummary
{
    public function countForMember(AuthenticatedMemberContext $context, int $memberId): int;
}
