<?php

declare(strict_types=1);

namespace app\Modules\Fixture\DeliveryRecord\Infrastructure\Persistence;

use PDO;
use PeanutAdmin\Kernel\Auth\TenantContext;

final readonly class PdoDeliveryRecordRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    /** @return array{id:int,tenant_id:int,reference:string,status:string} */
    public function create(TenantContext $context, string $reference): array
    {
        $statement = $this->pdo->prepare(<<<'SQL'
INSERT INTO pa_fixture_delivery_record (tenant_id, reference, status, created_at, updated_at)
VALUES (:tenant_id, :reference, 'recorded', :created_at, :updated_at)
SQL);
        $now = gmdate('Y-m-d H:i:s.v');
        $statement->execute([
            'tenant_id' => $context->tenantId,
            'reference' => $reference,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return [
            'id' => (int)$this->pdo->lastInsertId(),
            'tenant_id' => $context->tenantId,
            'reference' => $reference,
            'status' => 'recorded',
        ];
    }

    /** @return list<array{id:int,tenant_id:int,reference:string,status:string}> */
    public function all(TenantContext $context): array
    {
        $statement = $this->pdo->prepare(<<<'SQL'
SELECT id, tenant_id, reference, status
FROM pa_fixture_delivery_record
WHERE tenant_id = :tenant_id
ORDER BY id
SQL);
        $statement->execute(['tenant_id' => $context->tenantId]);

        return array_map(
            static fn(array $row): array => [
                'id' => (int)$row['id'],
                'tenant_id' => (int)$row['tenant_id'],
                'reference' => (string)$row['reference'],
                'status' => (string)$row['status'],
            ],
            $statement->fetchAll(PDO::FETCH_ASSOC)
        );
    }
}
