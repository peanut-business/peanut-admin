<?php
declare(strict_types=1);

namespace app\common\model\article;

use app\common\model\BaseModel;

class ArticleCollect extends BaseModel
{
    protected $name       = 'article_collect';
    protected $updateTime = false;

    /** 判断某用户是否收藏了某篇文章 */
    public static function isCollected(int $memberId, int $articleId): bool
    {
        if (!$memberId) return false;
        return self::where('member_id', $memberId)->where('article_id', $articleId)->count() > 0;
    }
}
