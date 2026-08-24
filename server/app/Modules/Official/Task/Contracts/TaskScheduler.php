<?php
declare(strict_types=1);

namespace app\Modules\Official\Task\Contracts;

use PeanutAdmin\Kernel\Tenancy\TenantScope;

interface TaskScheduler
{
    public function runDue(int $now): void;

    /** @param array<string,mixed> $item */
    public function start(TenantScope $scope, array $item): void;
}
