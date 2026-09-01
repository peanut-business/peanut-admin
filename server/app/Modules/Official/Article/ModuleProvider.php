<?php
declare(strict_types=1);

namespace app\Modules\Official\Article;

use PDO;
use app\common\composition\ModuleBindingContributor;
use app\Modules\Official\Article\Application\ArticleAdministrationService;
use app\Modules\Official\Article\Application\ArticleCollectionSummaryService;
use app\Modules\Official\Article\Application\ArticleQueryService;
use app\Modules\Official\Article\Application\PublicArticleService;
use app\Modules\Official\Article\Contracts\ArticleAdministration;
use app\Modules\Official\Article\Contracts\ArticleCollectionCommands;
use app\Modules\Official\Article\Contracts\ArticleCollectionSummary;
use app\Modules\Official\Article\Contracts\ArticleModuleAccess;
use app\Modules\Official\Article\Contracts\ArticleQueries;
use app\Modules\Official\Article\Contracts\PublicArticleQueries;
use app\Modules\Official\Article\Infrastructure\Authorization\PdoArticleModuleAccess;
use app\common\execution\CurrentExecutionContext;
use app\common\service\ProductAssetReferenceService;
use app\common\service\RichTextResourceService;
use PeanutAdmin\Kernel\Module\ModuleProvider as ModuleProviderContract;
use think\App;

final class ModuleProvider implements ModuleProviderContract, ModuleBindingContributor
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

    public function bindings(): array
    {
        return [
            ArticleModuleAccess::class => fn(App $app): ArticleModuleAccess => $this->access($app->make(PDO::class)),
            ArticleCollectionSummary::class => fn(): ArticleCollectionSummary => $this->collectionSummary(),
            ArticleQueries::class => fn(): ArticleQueries => new ArticleQueryService(),
            PublicArticleService::class => fn(App $app): PublicArticleService => new PublicArticleService(
                $app->make(ProductAssetReferenceService::class),
                $app->make(RichTextResourceService::class),
            ),
            PublicArticleQueries::class => fn(App $app): PublicArticleQueries => $app->make(PublicArticleService::class),
            ArticleCollectionCommands::class => fn(App $app): ArticleCollectionCommands => $app->make(PublicArticleService::class),
            ArticleAdministration::class => fn(App $app): ArticleAdministration => new ArticleAdministrationService(
                $app->make(CurrentExecutionContext::class),
                $app->make(\PeanutAdmin\Kernel\Persistence\TransactionManager::class),
                $app->make(ProductAssetReferenceService::class),
                $app->make(RichTextResourceService::class),
            ),
        ];
    }
}
