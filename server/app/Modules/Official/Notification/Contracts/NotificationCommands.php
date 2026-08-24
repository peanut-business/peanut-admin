<?php
declare(strict_types=1);

namespace app\Modules\Official\Notification\Contracts;

use PeanutAdmin\Kernel\Auth\TenantContext;

interface NotificationCommands
{
    public function saveChannel(TenantContext $context, string $section, array $input): void;

    public function saveScene(TenantContext $context, array $params): void;
}
