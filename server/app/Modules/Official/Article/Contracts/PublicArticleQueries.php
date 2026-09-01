<?php
declare(strict_types=1);

namespace app\Modules\Official\Article\Contracts;

use app\common\http\PageResult;

/** Public member and storefront reads owned by the Article Module. */
interface PublicArticleQueries
{
    public function lists(array $params, int $memberId = 0): PageResult;

    /** @return list<array<string,mixed>> */
    public function categories(): array;

    /** @return array<string,mixed> */
    public function detail(int $id, int $memberId = 0): array;

    public function collectionLists(int $memberId, array $params): PageResult;

    /** @return list<array<string,mixed>> */
    public function infoCenter(): array;

    /** @return list<array<string,mixed>> */
    public function homeArticles(int $limit): array;

    /** @return array<string,mixed> */
    public function pcDetail(int $memberId, int $articleId, string $source = 'default'): array;

    /** @return list<array<string,mixed>> */
    public function limitArticles(string $sortType, int $limit = 0, int $cid = 0, int $excludeId = 0): array;
}
