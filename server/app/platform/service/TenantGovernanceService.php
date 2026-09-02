<?php
declare(strict_types=1);

namespace app\platform\service;

use app\common\application\BusinessException;
use app\platform\identity\PlatformOperatorIdentityPort;
use DateTimeImmutable;
use PeanutAdmin\Kernel\Platform\Application\PlatformTenantAdminService;
use PeanutAdmin\Kernel\Platform\Bootstrap\BootstrapService;
use PeanutAdmin\Kernel\Persistence\TransactionManager;
use PeanutAdmin\Kernel\Tenancy\TenantStatus;

/**
 * Application adapter for the instance-local Tenant control plane.
 *
 * It accepts no administrator id or request tenant id as authority. The caller must inject a
 * trusted platform identity port; until one exists, use UnavailablePlatformOperatorIdentityPort.
 */
final readonly class TenantGovernanceService
{
    public function __construct(
        private PlatformOperatorIdentityPort $identities,
        private TransactionManager $transactions,
        private BootstrapService $bootstrap,
        private PlatformTenantAdminService $administration,
        private TenantOwnerAdminProvisioner $ownerAdmins
    ) {
    }

    /** @return array{tenant_id:int,account_id:int,member_id:int,role_id:int,status:string} */
    public function provision(
        string $operatorCredential,
        string $tenantCode,
        string $tenantName,
        string $ownerEmail,
        ?string $initialPassword,
        string $ownerDisplayName,
        string $requestId
    ): array {
        try {
            $operator = $this->identities->requireActive($operatorCredential);
            return $this->transactions->run(function () use (
                $operator,
                $tenantCode,
                $tenantName,
                $ownerEmail,
                $initialPassword,
                $ownerDisplayName,
                $requestId
            ): array {
                $candidate = $this->bootstrap->provisionTenantOwnerCandidate(
                    $operator->operatorId,
                    $tenantCode,
                    $tenantName,
                    $ownerEmail,
                    $initialPassword,
                    $ownerDisplayName,
                    $requestId
                );
                $this->bootstrap->activateTenantOwner(
                    $operator->operatorId,
                    $candidate->tenantId,
                    $candidate->memberId,
                    $requestId . ':owner-activation'
                );
                $this->ownerAdmins->provision(
                    $candidate->tenantId,
                    $candidate->accountId,
                    $candidate->memberId,
                    $candidate->roleId,
                    $tenantCode,
                    $ownerDisplayName
                );

                return $candidate->toArray();
            });
        } catch (\DomainException|\InvalidArgumentException) {
            throw BusinessException::conflict(
                'TENANT_PROVISION_REJECTED',
                'Tenant provisioning was rejected.',
            );
        }
    }

    /** @return array<string,mixed> */
    public function transition(
        string $operatorCredential,
        int $tenantId,
        int $expectedRevision,
        TenantStatus $next,
        string $changeReason,
        string $requestId
    ): array {
        try {
            $operator = $this->identities->requireActive($operatorCredential);
            return $this->administration->transitionTenant(
                $operator->operatorId,
                $operator->accountId,
                $tenantId,
                $expectedRevision,
                $next,
                $changeReason,
                $requestId
            );
        } catch (\DomainException|\InvalidArgumentException) {
            [$errorCode, $message] = match ($next) {
                TenantStatus::Active => ['TENANT_ACTIVATION_REJECTED', 'Tenant activation was rejected.'],
                TenantStatus::Suspended => ['TENANT_SUSPENSION_REJECTED', 'Tenant suspension was rejected.'],
                TenantStatus::Closed => ['TENANT_CLOSURE_REJECTED', 'Tenant closure was rejected.'],
                default => ['TENANT_TRANSITION_REJECTED', 'Tenant transition was rejected.'],
            };
            throw BusinessException::conflict($errorCode, $message);
        }
    }

    /** @param array<string,mixed> $config @return array<string,mixed> */
    public function enableModule(
        string $operatorCredential,
        int $tenantId,
        string $moduleKey,
        array $config,
        string $source,
        ?DateTimeImmutable $effectiveAt,
        ?DateTimeImmutable $expiresAt,
        string $changeReason,
        string $requestId
    ): array {
        $operator = $this->identities->requireActive($operatorCredential);

        return $this->administration->enableModule(
            $operator->operatorId,
            $operator->accountId,
            $tenantId,
            $moduleKey,
            $config,
            $source,
            $effectiveAt,
            $expiresAt,
            $changeReason,
            $requestId
        );
    }

    /** @return array<string,mixed> */
    public function disableModule(
        string $operatorCredential,
        int $tenantId,
        string $moduleKey,
        string $changeReason,
        string $requestId
    ): array {
        $operator = $this->identities->requireActive($operatorCredential);

        return $this->administration->disableModule(
            $operator->operatorId,
            $operator->accountId,
            $tenantId,
            $moduleKey,
            $changeReason,
            $requestId
        );
    }
}
