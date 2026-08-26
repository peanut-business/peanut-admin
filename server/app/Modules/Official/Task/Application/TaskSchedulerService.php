<?php
declare(strict_types=1);

namespace app\Modules\Official\Task\Application;

use app\Modules\Official\Task\Contracts\TaskScheduler;
use app\common\service\crontab\CrontabSchedulerService;
use PeanutAdmin\Kernel\Tenancy\TenantScope;

final readonly class TaskSchedulerService implements TaskScheduler
{
    public function runDue(int $now): void
    {
        CrontabSchedulerService::runDue($now);
    }

    public function start(TenantScope $scope, array $item): void
    {
        CrontabSchedulerService::start($scope, $item);
    }
}
