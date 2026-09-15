<?php
declare(strict_types=1);

namespace app\modules\fixture\delivery_record\services;

interface DeliveryRecordAccess
{
    public function requirePermission(string $permission): void;
}
