<?php
declare(strict_types=1);

namespace app\Modules\Official\Article\Contracts;

interface ArticleCollectionCommands
{
    public function add(int $articleId, int $memberId): void;

    public function cancel(int $articleId, int $memberId): void;
}
