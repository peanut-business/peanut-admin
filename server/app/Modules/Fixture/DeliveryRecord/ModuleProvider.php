<?php

declare(strict_types=1);

namespace app\Modules\Fixture\DeliveryRecord;

use PDO;
use app\Modules\Fixture\DeliveryRecord\Application\DeliveryRecordService;
use app\Modules\Fixture\DeliveryRecord\Contracts\DeliveryRecordCommands;
use app\Modules\Fixture\DeliveryRecord\Infrastructure\Authorization\PdoDeliveryRecordAccess;
use app\Modules\Fixture\DeliveryRecord\Infrastructure\Persistence\PdoDeliveryRecordRepository;
use PeanutAdmin\Kernel\Module\ModuleProvider as ModuleProviderContract;

final class ModuleProvider implements ModuleProviderContract
{
    public function moduleKey(): string
    {
        return 'fixture.delivery-record';
    }

    public function commands(PDO $pdo): DeliveryRecordCommands
    {
        return new DeliveryRecordService(
            new PdoDeliveryRecordRepository($pdo),
            new PdoDeliveryRecordAccess($pdo)
        );
    }
}
