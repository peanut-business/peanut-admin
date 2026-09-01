<?php
declare(strict_types=1);

namespace app\Modules\Official\Notification;

use app\common\composition\ModuleBindingContributor;
use app\common\execution\CurrentExecutionContext;
use app\common\execution\ExecutionContextAccess;
use app\common\service\http\OutboundHttpTransport;
use app\common\service\notice\ApplicationNoticeSmsSender;
use app\common\service\notice\NoticeSmsSender;
use app\common\service\notice\NoticeChannelService;
use app\common\service\external\ExternalChannelBindingService;
use app\common\service\external\ExternalTenantResolver;
use app\Modules\Official\Notification\Application\VerificationCodeService;
use app\Modules\Official\Notification\Application\NotificationApplicationService;
use app\Modules\Official\Notification\Application\NotificationBootstrapService;
use app\Modules\Official\Notification\Contracts\NotificationBootstrapCommands;
use app\Modules\Official\Notification\Contracts\NotificationCommands;
use app\Modules\Official\Notification\Contracts\NotificationQueries;
use app\Modules\Official\Notification\Contracts\VerificationCodeCommands;
use PeanutAdmin\Kernel\Module\ModuleProvider as ModuleProviderContract;
use PeanutAdmin\Kernel\Persistence\TransactionManager;
use think\App;

final class ModuleProvider implements ModuleProviderContract, ModuleBindingContributor
{
    public function moduleKey(): string
    {
        return 'official.notification';
    }

    public function bindings(): array
    {
        return [
            NoticeChannelService::class => fn(App $app): NoticeChannelService => new NoticeChannelService(
                $app->make(ExternalChannelBindingService::class),
                $app->make(ExternalTenantResolver::class),
                $app->make(OutboundHttpTransport::class),
            ),
            NoticeSmsSender::class => fn(App $app): NoticeSmsSender => new ApplicationNoticeSmsSender(
                $app->make(ExecutionContextAccess::class),
                $app->make(NoticeChannelService::class),
                (string)env('APP_ENV', '') === 'development',
            ),
            VerificationCodeService::class => fn(App $app): VerificationCodeService => new VerificationCodeService(
                $app->make(NoticeSmsSender::class),
                $app->make(TransactionManager::class),
                $app->make(ExecutionContextAccess::class),
                (string)env('APP_ENV', '') === 'development',
            ),
            NotificationApplicationService::class => fn(App $app): NotificationApplicationService => new NotificationApplicationService(
                $app->make(CurrentExecutionContext::class),
                $app->make(VerificationCodeService::class),
                $app->make(ExecutionContextAccess::class),
                $app->make(NoticeChannelService::class),
            ),
            NotificationCommands::class => fn(App $app): NotificationCommands => $app->make(NotificationApplicationService::class),
            NotificationBootstrapCommands::class => NotificationBootstrapService::class,
            NotificationQueries::class => fn(App $app): NotificationQueries => $app->make(NotificationApplicationService::class),
            VerificationCodeCommands::class => fn(App $app): VerificationCodeCommands => $app->make(NotificationApplicationService::class),
        ];
    }
}
