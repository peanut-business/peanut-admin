<?php
declare(strict_types=1);

namespace app\common\persistence;

use PeanutAdmin\Kernel\Persistence\Tenancy\TenantPersistenceMode;

/** Edition-shaped configuration consumed by Core's native ThinkPHP stores. */
final readonly class TenantPersistenceConfiguration
{
    public function __construct(
        public TenantPersistenceMode $mode,
        public ?int $instanceTenantId,
    ) {
        if ($mode === TenantPersistenceMode::InstanceScoped && ($instanceTenantId === null || $instanceTenantId < 1)) {
            throw new \RuntimeException('TENANT_PERSISTENCE_INSTANCE_TENANT_UNAVAILABLE');
        }
        if ($mode === TenantPersistenceMode::TenantScoped && $instanceTenantId !== null) {
            throw new \RuntimeException('TENANT_PERSISTENCE_SCOPE_INVALID');
        }
    }
}
