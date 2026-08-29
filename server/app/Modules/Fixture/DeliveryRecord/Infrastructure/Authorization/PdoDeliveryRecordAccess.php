<?php
declare(strict_types=1);

namespace app\Modules\Fixture\DeliveryRecord\Infrastructure\Authorization;

use app\Modules\Fixture\DeliveryRecord\Application\DeliveryRecordAccess;
use app\common\execution\CurrentExecutionContext;
use PDO;
use PeanutAdmin\Kernel\Authorization\PdoTenantAuthorizationRepository;
use PeanutAdmin\Kernel\Module\ModuleException;

final readonly class PdoDeliveryRecordAccess implements DeliveryRecordAccess
{
    public function __construct(
        private PDO $pdo,
        private CurrentExecutionContext $executionContext,
    )
    {
    }

    public function requirePermission(string $permission): void
    {
        $context = $this->executionContext->tenantAdmin();
        $permissions = (new PdoTenantAuthorizationRepository($this->pdo))->permissions(
            $context->tenantId,
            $context->memberId,
        );
        if (!$permissions->allows($permission)) {
            throw new ModuleException('AUTHORIZATION_PERMISSION_DENIED', 'Member permission is required.');
        }
    }
}
