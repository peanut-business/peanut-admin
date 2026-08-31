<?php
declare(strict_types=1);

namespace app\Modules\Official\Task\Contracts;

use PeanutAdmin\TaskJob\Application\TaskJobService;
use PeanutAdmin\TaskJob\Submission\TaskSubmissionProvider;
use PeanutAdmin\TaskJob\Submission\TrustedJobPublisher;
use PeanutAdmin\Kernel\Tenancy\TenantScope;

interface TaskJobRuntime
{
    public function publisher(TaskSubmissionProvider ...$providers): TrustedJobPublisher;

    public function jobs(): TaskJobService;

    public function enqueueCrontab(TenantScope $scope, int $scheduleId, string $contextIdentity): void;

    public function runTenant(int $tenantId, string $workerId, TaskWorkerDefinition ...$definitions): int;
}
