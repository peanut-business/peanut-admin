<?php
declare(strict_types=1);

namespace app\modules\official\task\contracts;

use PeanutAdmin\Kernel\Tenancy\TenantScope;

interface TaskScheduler
{
    public function runDue(int $now): void;

    /** @param array<string,mixed> $item */
    public function start(TenantScope $scope, array $item): void;
}
