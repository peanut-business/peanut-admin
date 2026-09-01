<?php
declare(strict_types=1);

namespace app\Modules\Official\Notification;

use app\common\composition\ModuleBindingContributor;
use app\Modules\Official\Notification\Application\NotificationApplicationService;
use app\Modules\Official\Notification\Contracts\NotificationCommands;
use app\Modules\Official\Notification\Contracts\NotificationQueries;
use app\Modules\Official\Notification\Contracts\VerificationCodeCommands;
use PeanutAdmin\Kernel\Module\ModuleProvider as ModuleProviderContract;
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
            NotificationApplicationService::class => fn(): NotificationApplicationService => new NotificationApplicationService(),
            NotificationCommands::class => fn(App $app): NotificationCommands => $app->make(NotificationApplicationService::class),
            NotificationQueries::class => fn(App $app): NotificationQueries => $app->make(NotificationApplicationService::class),
            VerificationCodeCommands::class => fn(App $app): VerificationCodeCommands => $app->make(NotificationApplicationService::class),
        ];
    }
}
