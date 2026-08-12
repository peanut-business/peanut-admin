<?php
declare(strict_types=1);

namespace app\common\service\tenant;

use PeanutAdmin\Kernel\Auth\TenantContext;
use PeanutAdmin\Kernel\Tenancy\TenantRepository;
use PeanutAdmin\Kernel\Tenancy\TenantStatus;

/** Server-side stop line for session creation and tenant-owned business writes. */
final readonly class TenantAvailabilityGuard
{
    public function __construct(private TenantRepository $tenants)
    {
    }

    public function assertNewSessionAllowed(int $tenantId): void
    {
        $this->assertActive($tenantId);
    }

    public function assertBusinessWriteAllowed(TenantContext $context): void
    {
        if ($context->tenantId <= 0 || $context->sessionKey === '' || $context->requestId === '') {
            throw new \DomainException('TRUSTED_TENANT_CONTEXT_REQUIRED');
        }
        $this->assertActive($context->tenantId);
    }

    private function assertActive(int $tenantId): void
    {
        if ($tenantId <= 0) {
            throw new \DomainException('TRUSTED_TENANT_CONTEXT_REQUIRED');
        }
        $tenant = $this->tenants->byId($tenantId);
        if ($tenant === null || $tenant->status !== TenantStatus::Active) {
            throw new \DomainException('TENANT_UNAVAILABLE');
        }
    }
}
