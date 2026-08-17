<?php
declare(strict_types=1);

namespace app\Modules\Official\Article;

use PDO;
use app\Modules\Official\Article\Application\ArticleCollectionSummaryService;
use app\Modules\Official\Article\Contracts\ArticleCollectionSummary;
use app\Modules\Official\Article\Contracts\ArticleModuleAccess;
use app\Modules\Official\Article\Infrastructure\Authorization\PdoArticleModuleAccess;
use PeanutAdmin\Kernel\Module\ModuleProvider as ModuleProviderContract;

final class ModuleProvider implements ModuleProviderContract
{
    public function moduleKey(): string
    {
        return 'official.article';
    }

    public function access(PDO $pdo): ArticleModuleAccess
    {
        return new PdoArticleModuleAccess($pdo);
    }

    public function collectionSummary(): ArticleCollectionSummary
    {
        return new ArticleCollectionSummaryService();
    }
}
