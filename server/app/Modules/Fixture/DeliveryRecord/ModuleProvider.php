<?php

declare(strict_types=1);

namespace app\Modules\Fixture\DeliveryRecord;

use PDO;
use app\Modules\Fixture\DeliveryRecord\Application\DeliveryRecordService;
use app\Modules\Fixture\DeliveryRecord\Contracts\DeliveryRecordCommands;
use app\Modules\Fixture\DeliveryRecord\Infrastructure\Authorization\PdoDeliveryRecordAccess;
use app\Modules\Fixture\DeliveryRecord\Infrastructure\Persistence\PdoDeliveryRecordRepository;
use app\common\execution\CurrentExecutionContext;
use PeanutAdmin\Kernel\Module\ModuleProvider as ModuleProviderContract;
use think\App;
use think\facade\Db;

final class ModuleProvider implements ModuleProviderContract
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

    public function register(App $app): void
    {
        $app->bind(DeliveryRecordCommands::class, function () use ($app): DeliveryRecordCommands {
            $pdo = Db::connect()->connect();
            if (!$pdo instanceof PDO) {
                throw new \RuntimeException('FIXTURE_DELIVERY_RECORD_DATABASE_UNAVAILABLE');
            }
            return $this->commands($pdo, $app->make(CurrentExecutionContext::class));
        });
    }
}
