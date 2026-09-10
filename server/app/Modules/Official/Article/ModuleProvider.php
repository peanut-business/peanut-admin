<?php
declare(strict_types=1);

namespace app\Modules\Official\Article;

use app\Modules\Official\Article\Application\ArticleAdministrationService;
use app\Modules\Official\Article\Application\ArticleQueryService;
use app\Modules\Official\Article\Application\PublicArticleService;
use app\Modules\Official\Article\Contracts\ArticleAdministration;
use app\Modules\Official\Article\Contracts\ArticleModuleAccess;
use app\Modules\Official\Article\Contracts\ArticleQueries;
use app\Modules\Official\Article\Contracts\PublicArticleQueries;
use app\Modules\Official\Article\Infrastructure\Authorization\PdoArticleModuleAccess;
use PeanutAdmin\Kernel\Module\ModuleProvider as ModuleProviderContract;

final class ModuleProvider implements ModuleProviderContract
{
    public function moduleKey(): string
    {
        return 'official.article';
    }

    public function bindings(): array
    {
        return [
            ArticleModuleAccess::class => PdoArticleModuleAccess::class,
            ArticleQueries::class => ArticleQueryService::class,
            PublicArticleQueries::class => PublicArticleService::class,
            ArticleAdministration::class => ArticleAdministrationService::class,
        ];
    }
}
