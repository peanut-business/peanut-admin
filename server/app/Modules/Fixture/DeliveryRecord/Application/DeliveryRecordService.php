<?php

declare(strict_types=1);

namespace app\Modules\Fixture\DeliveryRecord\Application;

use InvalidArgumentException;
use app\Modules\Fixture\DeliveryRecord\Contracts\DeliveryRecordCommands;
use app\Modules\Fixture\DeliveryRecord\Infrastructure\Persistence\PdoDeliveryRecordRepository;
use PeanutAdmin\Kernel\Auth\TenantContext;

final readonly class DeliveryRecordService implements DeliveryRecordCommands
{
    public function __construct(
        private PdoDeliveryRecordRepository $records,
        private DeliveryRecordAccess $access
    ) {}

    public function record(TenantContext $context, string $reference): array
    {
        $this->access->requirePermission($context, 'fixture.delivery-record.create');
        $reference = trim($reference);
        if ($reference === '' || strlen($reference) > 96) {
            throw new InvalidArgumentException('Delivery reference must contain at most 96 bytes.');
        }

        return $this->records->create($context, $reference);
    }

    public function list(TenantContext $context): array
    {
        $this->access->requirePermission($context, 'fixture.delivery-record.read');
        return $this->records->all($context);
    }
}
