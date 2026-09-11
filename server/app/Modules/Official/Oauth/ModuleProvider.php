<?php
declare(strict_types=1);

namespace app\Modules\Official\Oauth;

use app\common\service\external\ExternalTenantBindingRepository;
use app\common\service\external\ExternalChannelBindingService;
use app\common\service\external\ExternalChannelBindingStore;
use app\common\service\external\ExternalTenantAudit;
use app\common\service\external\ExternalTenantResolver;
use app\common\service\external\ThinkPhpExternalTenantBindingRepository;
use app\Modules\Official\Oauth\Application\OAuthQueryService;
use app\Modules\Official\Oauth\Application\OAuthCommandService;
use app\Modules\Official\Oauth\Contracts\OAuthCallbackLocator;
use app\Modules\Official\Oauth\Contracts\OfficialAccountCallbacks;
use app\Modules\Official\Oauth\Contracts\OAuthCommands;
use app\Modules\Official\Oauth\Contracts\OAuthPersistence;
use app\Modules\Official\Oauth\Contracts\OAuthQueries;
use app\Modules\Official\Oauth\Infrastructure\Persistence\ThinkPhpOAuthCallbackLocator;
use app\Modules\Official\Oauth\Infrastructure\Persistence\ThinkPhpOAuthPersistence;
use app\common\service\oauth\WechatOAuthTransport;
use PeanutAdmin\IntegrationSecurity\OAuth\OAuthTransport;
use PeanutAdmin\Kernel\Module\ModuleProvider as ModuleProviderContract;
use think\App;

final class ModuleProvider implements ModuleProviderContract
{
    public function moduleKey(): string
    {
        return 'official.oauth';
    }

    public function queries(): OAuthQueries
    {
        return new OAuthQueryService(new ThinkPhpOAuthPersistence());
    }

    public function bindings(): array
    {
        return [
            OAuthCallbackLocator::class => ThinkPhpOAuthCallbackLocator::class,
            OAuthPersistence::class => ThinkPhpOAuthPersistence::class,
            OAuthTransport::class => WechatOAuthTransport::class,
            OfficialAccountCallbacks::class => \app\Modules\Official\Oauth\Application\OfficialAccountApplicationService::class,
            ExternalTenantBindingRepository::class => ThinkPhpExternalTenantBindingRepository::class,
            ExternalChannelBindingStore::class => ThinkPhpExternalTenantBindingRepository::class,
            OAuthCommands::class => fn(App $app): OAuthCommands => new OAuthCommandService(
                $app->make(\app\Modules\Official\Member\Contracts\MemberQueries::class),
                $app->make(\app\Modules\Official\Member\Contracts\MemberIdentityCommands::class),
                $app->make(\app\Modules\Official\Member\Contracts\MemberProfileCommands::class),
                $app->make(\app\Modules\Official\Notification\Contracts\VerificationCodeCommands::class),
                $app->make(\app\common\persistence\AdvisoryLockExecution::class),
                $app->make(\app\common\persistence\TransactionalExecution::class),
                $app->make(\app\common\service\config\TenantApplicationSettingService::class),
                $app->make(ExternalTenantResolver::class),
                $app->make(OAuthPersistence::class),
                $app->make(OAuthTransport::class),
                (string)$app->config->get('project.default_image.user_avatar', ''),
            ),
            OAuthQueries::class => OAuthQueryService::class,
        ];
    }
}
