<?php
declare(strict_types=1);

namespace app\Modules\Official\Notification;

use PDO;
use app\common\execution\CurrentExecutionContext;
use app\Modules\Official\Notification\Application\NotificationApplicationService;
use app\Modules\Official\Notification\Contracts\NotificationCommands;
use app\Modules\Official\Notification\Contracts\NotificationQueries;
use app\Modules\Official\Notification\Contracts\VerificationCodeCommands;
use PeanutAdmin\Kernel\Module\ModuleProvider as ModuleProviderContract;
use think\App;

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

    public function register(App $app): void
    {
        $app->bind(NotificationApplicationService::class, fn(): NotificationApplicationService => new NotificationApplicationService(
            $app->make(PDO::class),
            $app->make(CurrentExecutionContext::class),
        ));
        $app->bind(NotificationCommands::class, fn(): NotificationCommands => $app->make(NotificationApplicationService::class));
        $app->bind(NotificationQueries::class, fn(): NotificationQueries => $app->make(NotificationApplicationService::class));
        $app->bind(VerificationCodeCommands::class, fn(): VerificationCodeCommands => $app->make(NotificationApplicationService::class));
    }
}
