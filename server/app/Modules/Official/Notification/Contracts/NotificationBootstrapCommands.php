<?php
declare(strict_types=1);

namespace app\Modules\Official\Notification\Contracts;

use app\common\execution\SystemExecutionContext;

interface NotificationBootstrapCommands
{
    public function provisionTenantDefaults(SystemExecutionContext $context): void;
}
