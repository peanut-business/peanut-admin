<?php
declare(strict_types=1);

namespace app\Modules\Official\Article\Model;

use app\common\model\BaseModel;
use app\common\service\article\ArticleTenantRepository;
use PeanutAdmin\Kernel\Auth\TenantContext;
use think\model\concern\SoftDelete;

class ArticleCollect extends BaseModel
{
    use SoftDelete;

    protected $name       = 'article_collect';
    protected $deleteTime = 'delete_time';

    /** 判断某用户是否收藏了某篇文章 */
    public static function isCollected(TenantContext $context, int $memberId, int $articleId): bool
    {
        if (!$memberId) return false;
        return ArticleTenantRepository::collections($context)->where('member_id', $memberId)
            ->where('article_id', $articleId)
            ->where('status', 1)
            ->count() > 0;
    }
}
