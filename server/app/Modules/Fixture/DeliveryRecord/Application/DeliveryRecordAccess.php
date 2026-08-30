<?php
declare(strict_types=1);

namespace app\Modules\Fixture\DeliveryRecord\Application;

interface DeliveryRecordAccess
{
    public function requirePermission(string $permission): void;
}
