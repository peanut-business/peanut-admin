<?php

declare(strict_types=1);

namespace app\Modules\Fixture\DeliveryRecord\Application;

use InvalidArgumentException;
use app\Modules\Fixture\DeliveryRecord\Contracts\DeliveryRecordCommands;
use app\Modules\Fixture\DeliveryRecord\Infrastructure\Persistence\PdoDeliveryRecordRepository;
use app\common\execution\CurrentExecutionContext;

final readonly class DeliveryRecordService implements DeliveryRecordCommands
{
    public function __construct(
        private PdoDeliveryRecordRepository $records,
        private DeliveryRecordAccess $access,
        private CurrentExecutionContext $executionContext,
    ) {}

    public function record(string $reference): array
    {
        $this->executionContext->tenantAdmin();
        $this->access->requirePermission('fixture.delivery-record.create');
        $reference = trim($reference);
        if ($reference === '' || strlen($reference) > 96) {
            throw new InvalidArgumentException('Delivery reference must contain at most 96 bytes.');
        }

        return $this->records->create($reference);
    }

    public function list(): array
    {
        $this->executionContext->tenantAdmin();
        $this->access->requirePermission('fixture.delivery-record.read');
        return $this->records->all();
    }
}
