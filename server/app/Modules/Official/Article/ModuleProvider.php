<?php
declare(strict_types=1);

namespace app\Modules\Official\Article;

use PDO;
use app\Modules\Official\Article\Application\ArticleCollectionSummaryService;
use app\Modules\Official\Article\Contracts\ArticleAdministration;
use app\Modules\Official\Article\Contracts\ArticleCollectionSummary;
use app\Modules\Official\Article\Contracts\ArticleModuleAccess;
use app\Modules\Official\Article\Infrastructure\Authorization\PdoArticleModuleAccess;
use app\common\execution\CurrentExecutionContext;
use PeanutAdmin\Kernel\Module\ModuleProvider as ModuleProviderContract;
use think\App;

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

    public function administration(): ArticleAdministration
    {
        return app(ArticleAdministration::class);
    }

    public function register(App $app): void
    {
        $app->bind(ArticleCollectionSummary::class, fn(): ArticleCollectionSummary => new ArticleCollectionSummaryService());
        $app->bind(ArticleAdministration::class, fn(): ArticleAdministration => new ArticleAdministrationService(
            $app->make(CurrentExecutionContext::class),
        ));
    }
}
