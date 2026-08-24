<?php
declare(strict_types=1);

namespace app\Modules\Official\Task;

use app\Modules\Official\Task\Application\PdoTaskJobRuntime;
use app\Modules\Official\Task\Application\TaskSchedulerService;
use app\Modules\Official\Task\Contracts\TaskJobRuntime;
use app\Modules\Official\Task\Contracts\TaskScheduler;
use PeanutAdmin\Kernel\Module\ModuleProvider as ModuleProviderContract;
use PDO;

final class ModuleProvider implements ModuleProviderContract
{
    public function moduleKey(): string
    {
        return 'official.task';
    }

    public function scheduler(): TaskScheduler
    {
        return new TaskSchedulerService();
    }

    public function jobs(PDO $pdo, string $signingKey): TaskJobRuntime
    {
        return new PdoTaskJobRuntime($pdo, $signingKey);
    }
}
