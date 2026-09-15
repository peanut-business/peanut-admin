<?php
declare(strict_types=1);

namespace app\Modules\Official\Notification;

use app\common\execution\CurrentExecutionContext;
use app\common\service\http\OutboundHttpTransport;
use app\common\infrastructure\notice\ApplicationNoticeSmsSender;
use app\common\services\notice\NoticeChannelService;
use app\common\service\external\ExternalChannelBindingService;
use PeanutAdmin\IntegrationSecurity\External\ExternalTenantResolver;
use app\Modules\Official\Notification\Application\VerificationCodeService;
use app\Modules\Official\Notification\Application\NotificationApplicationService;
use app\Modules\Official\Notification\Application\NotificationBootstrapService;
use app\Modules\Official\Notification\Contracts\NotificationBootstrapCommands;
use app\Modules\Official\Notification\Contracts\NotificationCommands;
use app\Modules\Official\Notification\Contracts\NotificationQueries;
use app\Modules\Official\Notification\Contracts\VerificationCodeCommands;
use PeanutAdmin\Kernel\Module\ModuleProvider as ModuleProviderContract;
use PeanutAdmin\NotificationSms\Sms\NoticeSmsSender;
use think\App;
use think\facade\Config;

final class ModuleProvider implements ModuleProviderContract
{
    public function moduleKey(): string
    {
        return 'official.notification';
    }

    public function bindings(): array
    {
        return [
            NoticeSmsSender::class => fn(App $app): NoticeSmsSender => new ApplicationNoticeSmsSender(
                $app->make(CurrentExecutionContext::class),
                $app->make(NoticeChannelService::class),
                (string)Config::get('peanut.environment', '') === 'development',
            ),
            VerificationCodeService::class => fn(App $app): VerificationCodeService => new VerificationCodeService(
                $app->make(NoticeSmsSender::class),
                $app->make(CurrentExecutionContext::class),
                (string)Config::get('peanut.environment', '') === 'development',
            ),
            NotificationCommands::class => NotificationApplicationService::class,
            NotificationBootstrapCommands::class => NotificationBootstrapService::class,
            NotificationQueries::class => NotificationApplicationService::class,
            VerificationCodeCommands::class => NotificationApplicationService::class,
        ];
    }
}
