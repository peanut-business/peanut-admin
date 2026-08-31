<?php
declare(strict_types=1);

namespace app\Modules\Official\Notification;

use app\Modules\Official\Notification\Application\NotificationApplicationService;
use app\Modules\Official\Notification\Contracts\NotificationCommands;
use app\Modules\Official\Notification\Contracts\NotificationQueries;
use app\Modules\Official\Notification\Contracts\VerificationCodeCommands;
use PeanutAdmin\Kernel\Module\ModuleProvider as ModuleProviderContract;

final class ModuleProvider implements ModuleProviderContract
{
    public function moduleKey(): string
    {
        return 'official.notification';
    }

    public function commands(): NotificationCommands
    {
        return $this->application();
    }

    public function queries(): NotificationQueries
    {
        return $this->application();
    }

    public function verification(): VerificationCodeCommands
    {
        return $this->application();
    }

    private function application(): NotificationApplicationService
    {
        return app(NotificationApplicationService::class);
    }
}
