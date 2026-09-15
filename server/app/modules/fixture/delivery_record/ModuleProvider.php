<?php

declare(strict_types=1);

namespace app\modules\fixture\delivery_record;

use app\modules\fixture\delivery_record\services\DeliveryRecordService;
use app\modules\fixture\delivery_record\services\DeliveryRecordAccess;
use app\modules\fixture\delivery_record\contracts\DeliveryRecordCommands;
use app\modules\fixture\delivery_record\infrastructure\authorization\ThinkPhpDeliveryRecordAccess;
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
