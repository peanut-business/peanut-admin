<?php
declare(strict_types=1);

namespace app\Modules\Fixture\DeliveryRecord\Infrastructure\Authorization;

use PDO;
use app\Modules\Fixture\DeliveryRecord\Application\DeliveryRecordAccess;
use app\common\service\module\ModuleExecutionContext;
use app\platform\service\module\PdoModuleGovernanceProvider;
use PeanutAdmin\Kernel\Auth\TenantContext;

final readonly class PdoDeliveryRecordAccess implements DeliveryRecordAccess
{
    public function __construct(private PDO $pdo)
    {
    }

    public function requirePermission(TenantContext $context, string $permission): void
    {
        PdoModuleGovernanceProvider::forExecution($this->pdo)
            ->executionGuard('fixture.delivery-record')
            ->assertAdminPermission(
            ModuleExecutionContext::admin('fixture.delivery-record', $context, $permission),
            $permission,
        );
    }
}
