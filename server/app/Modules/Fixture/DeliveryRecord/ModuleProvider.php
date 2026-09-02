<?php

declare(strict_types=1);

namespace app\Modules\Fixture\DeliveryRecord;

use PDO;
use app\common\composition\ModuleBindingContributor;
use app\Modules\Fixture\DeliveryRecord\Application\DeliveryRecordService;
use app\Modules\Fixture\DeliveryRecord\Contracts\DeliveryRecordCommands;
use app\Modules\Fixture\DeliveryRecord\Infrastructure\Authorization\PdoDeliveryRecordAccess;
use app\Modules\Fixture\DeliveryRecord\Infrastructure\Persistence\PdoDeliveryRecordRepository;
use app\common\execution\CurrentExecutionContext;
use PeanutAdmin\Kernel\Module\ModuleProvider as ModuleProviderContract;
use think\App;

final class ModuleProvider implements ModuleProviderContract, ModuleBindingContributor
{
    public function moduleKey(): string
    {
        return 'fixture.delivery-record';
    }

    public function commands(PDO $pdo, CurrentExecutionContext $executionContext): DeliveryRecordCommands
    {
        return new DeliveryRecordService(
            new PdoDeliveryRecordRepository($pdo, $executionContext),
            new PdoDeliveryRecordAccess($pdo, $executionContext),
            $executionContext,
        );
    }

    public function bindings(): array
    {
        return [
            DeliveryRecordCommands::class => fn(App $app): DeliveryRecordCommands => $this->commands(
                $app->make(PDO::class),
                $app->make(CurrentExecutionContext::class),
            ),
        ];
    }
}
