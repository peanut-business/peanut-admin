<?php

declare(strict_types=1);

namespace app\Modules\Fixture\DeliveryRecord;

use app\Modules\Fixture\DeliveryRecord\Application\DeliveryRecordService;
use app\Modules\Fixture\DeliveryRecord\Application\DeliveryRecordAccess;
use app\Modules\Fixture\DeliveryRecord\Contracts\DeliveryRecordCommands;
use app\Modules\Fixture\DeliveryRecord\Infrastructure\Authorization\ThinkPhpDeliveryRecordAccess;
use PeanutAdmin\Kernel\Module\ModuleProvider as ModuleProviderContract;

final class ModuleProvider implements ModuleProviderContract
{
    public function moduleKey(): string
    {
        return 'fixture.delivery-record';
    }

    public function bindings(): array
    {
        return [
            DeliveryRecordAccess::class => ThinkPhpDeliveryRecordAccess::class,
            DeliveryRecordCommands::class => DeliveryRecordService::class,
        ];
    }
}
