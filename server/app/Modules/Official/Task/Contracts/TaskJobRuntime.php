<?php
declare(strict_types=1);

namespace app\Modules\Official\Task\Contracts;

use PeanutAdmin\TaskJob\Application\TaskJobService;
use PeanutAdmin\TaskJob\Submission\TaskSubmissionProvider;
use PeanutAdmin\TaskJob\Submission\TrustedJobPublisher;

interface TaskJobRuntime
{
    public function publisher(TaskSubmissionProvider ...$providers): TrustedJobPublisher;

    public function jobs(): TaskJobService;

    public function runTenant(int $tenantId, string $workerId, TaskWorkerDefinition ...$definitions): int;
}
