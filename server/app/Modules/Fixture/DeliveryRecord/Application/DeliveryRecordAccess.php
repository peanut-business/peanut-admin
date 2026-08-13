<?php
declare(strict_types=1);

namespace app\Modules\Fixture\DeliveryRecord\Application;

use PeanutAdmin\Kernel\Auth\TenantContext;

interface DeliveryRecordAccess
{
    public function requirePermission(TenantContext $context, string $permission): void;
}
