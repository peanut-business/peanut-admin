<?php
declare(strict_types=1);

namespace app\Modules\Official\Oauth;

use app\common\composition\ModuleBindingContributor;
use app\common\service\external\ExternalTenantBindingRepository;
use app\common\service\external\ExternalChannelBindingService;
use app\common\service\external\ExternalChannelBindingStore;
use app\common\service\external\ExternalTenantAudit;
use app\common\service\external\ExternalTenantResolver;
use app\common\service\external\ThinkPhpExternalTenantBindingRepository;
use app\Modules\Official\Oauth\Application\OAuthQueryService;
use app\Modules\Official\Oauth\Application\OAuthCommandService;
use app\Modules\Official\Oauth\Contracts\OAuthCallbackLocator;
use app\Modules\Official\Oauth\Contracts\OAuthCommands;
use app\Modules\Official\Oauth\Contracts\OAuthQueries;
use app\Modules\Official\Oauth\Infrastructure\Persistence\ThinkPhpOAuthCallbackLocator;
use app\api\application\OAuthApplicationService;
use PeanutAdmin\Kernel\Module\ModuleProvider as ModuleProviderContract;
use think\App;

final class ModuleProvider implements ModuleProviderContract, ModuleBindingContributor
{
    public function moduleKey(): string
    {
        return 'official.oauth';
    }

    public function queries(): OAuthQueries
    {
        return new OAuthQueryService();
    }

    public function bindings(): array
    {
        return [
            OAuthCallbackLocator::class => fn(): OAuthCallbackLocator => new ThinkPhpOAuthCallbackLocator(),
            ThinkPhpExternalTenantBindingRepository::class => fn(App $app): ThinkPhpExternalTenantBindingRepository => new ThinkPhpExternalTenantBindingRepository(
                $app->make(OAuthCallbackLocator::class),
            ),
            ExternalTenantBindingRepository::class => fn(App $app): ExternalTenantBindingRepository => $app->make(ThinkPhpExternalTenantBindingRepository::class),
            ExternalChannelBindingStore::class => fn(App $app): ExternalChannelBindingStore => $app->make(ThinkPhpExternalTenantBindingRepository::class),
            ExternalTenantResolver::class => fn(App $app): ExternalTenantResolver => new ExternalTenantResolver(
                $app->make(ExternalTenantBindingRepository::class),
                $app->make(ExternalTenantAudit::class),
            ),
            ExternalChannelBindingService::class => fn(App $app): ExternalChannelBindingService => new ExternalChannelBindingService(
                $app->make(ExternalTenantBindingRepository::class),
                $app->make(ExternalTenantResolver::class),
                $app->make(ExternalChannelBindingStore::class),
            ),
            OAuthCommands::class => fn(App $app): OAuthCommands => new OAuthCommandService(
                $app->make(OAuthApplicationService::class),
            ),
            OAuthQueries::class => fn(): OAuthQueries => $this->queries(),
        ];
    }
}
