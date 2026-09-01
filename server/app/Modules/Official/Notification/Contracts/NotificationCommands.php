<?php
declare(strict_types=1);

namespace app\Modules\Official\Notification\Contracts;

use app\common\execution\SystemExecutionContext;

interface NotificationCommands
{
    public function provisionTenantDefaults(SystemExecutionContext $context): void;

    public function saveChannel(string $section, array $input): void;

    public function saveScene(array $params): void;
}
