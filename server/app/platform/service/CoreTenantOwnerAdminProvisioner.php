<?php
declare(strict_types=1);

namespace app\platform\service;

use PeanutAdmin\Kernel\Identity\AccountStatus;
use PeanutAdmin\Kernel\Identity\IdentityRepository;
use PeanutAdmin\Kernel\Membership\MembershipRepository;
use PeanutAdmin\Kernel\Membership\TenantMemberStatus;

/** Verifies that Core already provisioned the first owner and initializes application capabilities. */
final readonly class CoreTenantOwnerAdminProvisioner implements TenantOwnerAdminProvisioner
{
    public function __construct(
        private IdentityRepository $identities,
        private MembershipRepository $memberships,
        private ApplicationTenantBootstrapService $applicationBootstrap,
    ) {}

    public function provision(
        int $tenantId,
        int $accountId,
        int $memberId,
        int $coreRoleId,
        string $tenantCode,
        string $displayName
    ): int {
        if (min($tenantId, $accountId, $memberId, $coreRoleId) < 1 || $tenantCode === '' || $displayName === '') {
            throw new \DomainException('TENANT_OWNER_ADMIN_PRINCIPAL_INVALID');
        }

        $account = $this->identities->accountById($accountId);
        $member = $this->memberships->byId($tenantId, $memberId);
        $ownerRole = $this->memberships->roleByKey($tenantId, 'core.tenant-owner');
        if ($account === null
            || $account->status !== AccountStatus::Active
            || $member === null
            || $member->accountId !== $accountId
            || $member->status !== TenantMemberStatus::Active
            || $ownerRole === null
            || $ownerRole->id !== $coreRoleId
            || !$ownerRole->isBuiltin
            || !$this->memberships->memberHasRole($tenantId, $memberId, 'core.tenant-owner')) {
            throw new \DomainException('TENANT_OWNER_ADMIN_PRINCIPAL_INVALID');
        }

        $this->applicationBootstrap->provision(
            $tenantId,
            $memberId,
            $coreRoleId,
            $tenantCode
        );

        return $memberId;
    }
}
