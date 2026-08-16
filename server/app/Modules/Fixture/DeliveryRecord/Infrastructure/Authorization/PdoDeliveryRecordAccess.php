<?php
declare(strict_types=1);

namespace app\Modules\Fixture\DeliveryRecord\Infrastructure\Authorization;

use DateTimeImmutable;
use DateTimeZone;
use PDO;
use app\Modules\Fixture\DeliveryRecord\Application\DeliveryRecordAccess;
use PeanutAdmin\Kernel\Auth\TenantContext;
use PeanutAdmin\Kernel\Authorization\PdoTenantAuthorizationRepository;
use PeanutAdmin\Kernel\Module\ModuleGuard;
use PeanutAdmin\Kernel\Module\Persistence\PdoModuleRuntimeRepository;

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
        (new ModuleGuard(new PdoModuleRuntimeRepository($this->pdo)))->assertMemberAccess(
            $context->tenantId,
            'fixture.delivery-record',
            $permissions->allows($permission),
            new DateTimeImmutable('now', new DateTimeZone('UTC'))
        );
    }
}
