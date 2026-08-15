<?php
declare(strict_types=1);

namespace app\Modules\Fixture\DeliveryRecord\Infrastructure\Authorization;

use DomainException;
use PDO;
use app\Modules\Fixture\DeliveryRecord\Application\DeliveryRecordAccess;
use PeanutAdmin\Kernel\Auth\TenantContext;
use PeanutAdmin\Kernel\Authorization\PdoTenantAuthorizationRepository;

final readonly class PdoDeliveryRecordAccess implements DeliveryRecordAccess
{
    public function __construct(private PDO $pdo)
    {
    }

    public function requirePermission(TenantContext $context, string $permission): void
    {
        $permissions = (new PdoTenantAuthorizationRepository($this->pdo))->permissions(
            $context->tenantId,
            $context->memberId
        );
        if (!$permissions->allows($permission)) {
            throw new DomainException('FIXTURE_DELIVERY_RECORD_FORBIDDEN');
        }
    }
}
