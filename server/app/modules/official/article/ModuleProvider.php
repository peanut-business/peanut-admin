<?php
declare(strict_types=1);

namespace app\modules\official\article;

use app\modules\official\article\services\ArticleAdministrationService;
use app\modules\official\article\services\ArticleQueryService;
use app\modules\official\article\services\PublicArticleService;
use app\modules\official\article\contracts\ArticleAdministration;
use app\modules\official\article\contracts\ArticleQueries;
use app\modules\official\article\contracts\PublicArticleQueries;
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
            ArticleQueries::class => ArticleQueryService::class,
            PublicArticleQueries::class => PublicArticleService::class,
            ArticleAdministration::class => ArticleAdministrationService::class,
        ];
    }
}
