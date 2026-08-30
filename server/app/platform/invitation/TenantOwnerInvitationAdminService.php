<?php
declare(strict_types=1);

namespace app\platform\invitation;

use app\common\service\audit\AuditContractHost;
use app\platform\context\PlatformOperatorContext;
use app\platform\service\PlatformOperatorSessionService;
use DateTimeImmutable;
use DateTimeZone;
use PDO;
use PeanutAdmin\Kernel\Audit\AuditOutcome;
use PeanutAdmin\Kernel\Authorization\Application\PageRequest;
use PeanutAdmin\Kernel\Identity\EmailAddress;
use PeanutAdmin\Kernel\Membership\MembershipRepository;
use PeanutAdmin\Kernel\Persistence\Pdo\PdoMembershipRepository;
use PeanutAdmin\Kernel\Persistence\Pdo\PdoTenantRepository;
use PeanutAdmin\Kernel\Persistence\Pdo\PdoTransactionManager;
use PeanutAdmin\Kernel\Tenancy\TenantStatus;

final class TenantOwnerInvitationAdminService
{
    private const OWNER_ROLE = 'core.tenant-owner';
    private const CREATE_PERMISSION = 'platform.tenant.create';
    private const INVITE_PERMISSION = 'platform.tenant.provision-owner';

    private PdoTransactionManager $transactions;
    private PdoTenantRepository $tenants;
    private MembershipRepository $memberships;
    private AuditContractHost $audit;
    private OwnerInvitationRuntimePolicy $runtimePolicy;

    public function __construct(
        private readonly PDO $pdo,
        private readonly PlatformOperatorSessionService $sessions,
        private readonly OwnerInvitationDeliveryPort $delivery,
        ?OwnerInvitationRuntimePolicy $runtimePolicy = null
    ) {
        $this->runtimePolicy = $runtimePolicy
            ?? OwnerInvitationRuntimePolicy::fromEnvironment((string)(getenv('APP_ENV') ?: ''));
        $this->transactions = new PdoTransactionManager($pdo);
        $this->tenants = new PdoTenantRepository($pdo);
        $this->memberships = new PdoMembershipRepository($pdo);
        $this->audit = AuditContractHost::fromPdo($pdo);
    }

    /** @return array<string,mixed> */
    public function provision(
        PlatformOperatorContext $context,
        string $tenantCode,
        string $tenantName,
        string $ownerEmail,
        string $ownerDisplayName,
        int $expiresInHours
    ): array {
        $this->sessions->assertAllowed($context, self::CREATE_PERMISSION);
        $this->sessions->assertAllowed($context, self::INVITE_PERMISSION);
        $this->runtimePolicy->assertIssuanceAllowed($this->delivery);
        $email = EmailAddress::fromString($ownerEmail)->value();
        $token = OneTimeInvitationToken::issue();
        $expiresAt = $this->expiry($expiresInHours);

        $issued = $this->transactions->run(function () use (
            $context,
            $tenantCode,
            $tenantName,
            $email,
            $ownerDisplayName,
            $token,
            $expiresAt
        ): array {
            if ($this->tenants->byCode($tenantCode, true) !== null) {
                throw TenantOwnerInvitationException::conflict(
                    'TENANT_CODE_EXISTS',
                    'Tenant code is already in use.'
                );
            }
            $tenant = $this->tenants->createProvisioning($tenantCode, $tenantName);
            $this->memberships->createBuiltinRole($tenant->id, self::OWNER_ROLE, 'Tenant Owner');
            $invitation = $this->insertInvitation(
                $tenant->id,
                $email,
                $ownerDisplayName,
                $token,
                $expiresAt,
                $context->core->operatorId
            );
            $this->audit->recordPlatform(
                'tenant.owner-invitation.created',
                self::INVITE_PERMISSION,
                $context->core->requestId,
                $context->core->operatorId,
                $context->core->accountId,
                ['tenant_id' => $tenant->id, 'invitation_id' => $invitation['id']],
                AuditOutcome::Success,
                null,
            );

            return $invitation + [
                'tenant_code' => $tenant->code,
                'tenant_name' => $tenant->name,
                'tenant_status' => TenantStatus::Provisioning->value,
            ];
        });

        return $this->deliver($issued, $token);
    }

    /** @return array<string,mixed> */
    public function invite(
        PlatformOperatorContext $context,
        int $tenantId,
        string $ownerEmail,
        string $ownerDisplayName,
        int $expiresInHours
    ): array {
        $this->sessions->assertAllowed($context, self::INVITE_PERMISSION);
        $this->runtimePolicy->assertIssuanceAllowed($this->delivery);
        $email = EmailAddress::fromString($ownerEmail)->value();
        $token = OneTimeInvitationToken::issue();
        $expiresAt = $this->expiry($expiresInHours);

        $issued = $this->transactions->run(function () use (
            $context,
            $tenantId,
            $email,
            $ownerDisplayName,
            $token,
            $expiresAt
        ): array {
            $tenant = $this->lockInvitableTenant($tenantId);
            $this->expireStalePending($tenantId);
            $this->assertOwnerBaseline($tenantId, (string)$tenant['status']);
            if ($this->pendingInvitationExists($tenantId)) {
                throw TenantOwnerInvitationException::conflict(
                    'TENANT_OWNER_INVITATION_PENDING',
                    'Tenant already has a pending owner invitation.'
                );
            }
            if ($this->memberships->roleByKey($tenantId, self::OWNER_ROLE, true) === null) {
                $this->memberships->createBuiltinRole($tenantId, self::OWNER_ROLE, 'Tenant Owner');
            }
            $invitation = $this->insertInvitation(
                $tenantId,
                $email,
                $ownerDisplayName,
                $token,
                $expiresAt,
                $context->core->operatorId
            );
            $this->audit->recordPlatform(
                'tenant.owner-invitation.created',
                self::INVITE_PERMISSION,
                $context->core->requestId,
                $context->core->operatorId,
                $context->core->accountId,
                ['tenant_id' => $tenantId, 'invitation_id' => $invitation['id']],
                AuditOutcome::Success,
                null,
            );

            return $invitation + [
                'tenant_code' => $tenant['code'],
                'tenant_name' => $tenant['name'],
                'tenant_status' => $tenant['status'],
            ];
        });

        return $this->deliver($issued, $token);
    }

    /** @return array{items:list<array<string,mixed>>,total:int} */
    public function invitations(
        PlatformOperatorContext $context,
        int $tenantId,
        PageRequest $page
    ): array {
        $this->sessions->assertAllowed($context, self::INVITE_PERMISSION);
        $count = $this->pdo->prepare(
            'SELECT COUNT(*) FROM pa_tenant_owner_invitation WHERE tenant_id = :tenant_id'
        );
        $count->execute(['tenant_id' => $tenantId]);
        $statement = $this->pdo->prepare(<<<'SQL'
SELECT id, tenant_id, email_normalized AS email, display_name,
       CASE WHEN status = 'pending' AND expires_at <= UTC_TIMESTAMP(3) THEN 'expired' ELSE status END AS status,
       delivery_status, delivery_provider, delivery_attempts, delivery_error_code,
       generation, expires_at, accepted_at, revoked_at, accepted_account_id, accepted_member_id,
       invited_by_operator_id, revoked_by_operator_id, created_at, updated_at
FROM pa_tenant_owner_invitation
WHERE tenant_id = :tenant_id
ORDER BY id DESC
LIMIT :limit OFFSET :offset
SQL);
        $statement->bindValue(':tenant_id', $tenantId, PDO::PARAM_INT);
        $statement->bindValue(':limit', $page->pageSize, PDO::PARAM_INT);
        $statement->bindValue(':offset', $page->offset(), PDO::PARAM_INT);
        $statement->execute();

        return [
            'items' => $statement->fetchAll(PDO::FETCH_ASSOC),
            'total' => (int)$count->fetchColumn(),
        ];
    }

    /** @return array<string,mixed> */
    public function resend(
        PlatformOperatorContext $context,
        int $invitationId,
        int $expiresInHours
    ): array {
        $this->sessions->assertAllowed($context, self::INVITE_PERMISSION);
        $this->runtimePolicy->assertIssuanceAllowed($this->delivery);
        $token = OneTimeInvitationToken::issue();
        $expiresAt = $this->expiry($expiresInHours);
        $issued = $this->transactions->run(function () use (
            $context,
            $invitationId,
            $expiresAt,
            $token
        ): array {
            $invitation = $this->lockInvitationById($invitationId);
            $tenantId = (int)$invitation['tenant_id'];
            $tenant = $this->lockInvitableTenant($tenantId);
            if ($invitation['status'] !== 'pending') {
                throw TenantOwnerInvitationException::conflict(
                    'INVITATION_NOT_PENDING',
                    'Only a pending invitation can be resent.'
                );
            }
            $this->assertOwnerBaseline($tenantId, (string)$tenant['status']);
            $now = $this->now();
            $statement = $this->pdo->prepare(<<<'SQL'
UPDATE pa_tenant_owner_invitation
SET token_hash = :token_hash,
    delivery_status = 'pending_delivery', delivery_provider = NULL, delivery_message_id = NULL,
    delivery_attempts = 0, delivery_error_code = NULL, last_delivery_at = NULL,
    generation = generation + 1, expires_at = :expires_at, updated_at = :updated_at
WHERE id = :id AND status = 'pending'
SQL);
            $statement->execute([
                'token_hash' => $token->hash(),
                'expires_at' => $this->format($expiresAt),
                'updated_at' => $this->format($now),
                'id' => $invitationId,
            ]);
            $this->audit->recordPlatform(
                'tenant.owner-invitation.resent',
                self::INVITE_PERMISSION,
                $context->core->requestId,
                $context->core->operatorId,
                $context->core->accountId,
                ['tenant_id' => (int)$invitation['tenant_id'], 'invitation_id' => $invitationId],
                AuditOutcome::Success,
                null,
            );

            return [
                'id' => $invitationId,
                'tenant_id' => $tenantId,
                'tenant_code' => $tenant['code'],
                'tenant_name' => $tenant['name'],
                'email' => $invitation['email_normalized'],
                'display_name' => $invitation['display_name'],
                'status' => 'pending',
                'delivery_status' => 'pending_delivery',
                'generation' => (int)$invitation['generation'] + 1,
                'expires_at' => $this->format($expiresAt),
            ];
        });

        return $this->deliver($issued, $token);
    }

    /** @return array{id:int,tenant_id:int,status:string} */
    public function revoke(PlatformOperatorContext $context, int $invitationId): array
    {
        $this->sessions->assertAllowed($context, self::INVITE_PERMISSION);

        return $this->transactions->run(function () use ($context, $invitationId): array {
            $invitation = $this->lockInvitationById($invitationId);
            $this->lockInvitableTenant((int)$invitation['tenant_id']);
            if ($invitation['status'] !== 'pending') {
                throw TenantOwnerInvitationException::conflict(
                    'INVITATION_NOT_PENDING',
                    'Only a pending invitation can be revoked.'
                );
            }
            $now = $this->format($this->now());
            $statement = $this->pdo->prepare(<<<'SQL'
UPDATE pa_tenant_owner_invitation
SET status = 'revoked', revoked_at = :revoked_at,
    revoked_by_operator_id = :operator_id, updated_at = :updated_at
WHERE id = :id AND status = 'pending'
SQL);
            $statement->execute([
                'revoked_at' => $now,
                'operator_id' => $context->core->operatorId,
                'updated_at' => $now,
                'id' => $invitationId,
            ]);
            $this->audit->recordPlatform(
                'tenant.owner-invitation.revoked',
                self::INVITE_PERMISSION,
                $context->core->requestId,
                $context->core->operatorId,
                $context->core->accountId,
                ['tenant_id' => (int)$invitation['tenant_id'], 'invitation_id' => $invitationId],
                AuditOutcome::Success,
                null,
            );

            return [
                'id' => $invitationId,
                'tenant_id' => (int)$invitation['tenant_id'],
                'status' => 'revoked',
            ];
        });
    }

    /** @return array<string,mixed> */
    private function insertInvitation(
        int $tenantId,
        string $email,
        string $displayName,
        OneTimeInvitationToken $token,
        DateTimeImmutable $expiresAt,
        int $operatorId
    ): array {
        $now = $this->format($this->now());
        $statement = $this->pdo->prepare(<<<'SQL'
INSERT INTO pa_tenant_owner_invitation (
    tenant_id, email_normalized, display_name, token_hash, expires_at,
    invited_by_operator_id, created_at, updated_at
) VALUES (
    :tenant_id, :email, :display_name, :token_hash, :expires_at,
    :operator_id, :created_at, :updated_at
)
SQL);
        $statement->execute([
            'tenant_id' => $tenantId,
            'email' => $email,
            'display_name' => $displayName,
            'token_hash' => $token->hash(),
            'expires_at' => $this->format($expiresAt),
            'operator_id' => $operatorId,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return [
            'id' => (int)$this->pdo->lastInsertId(),
            'tenant_id' => $tenantId,
            'email' => $email,
            'display_name' => $displayName,
            'status' => 'pending',
            'delivery_status' => 'pending_delivery',
            'generation' => 1,
            'expires_at' => $this->format($expiresAt),
        ];
    }

    /** @param array<string,mixed> $issued @return array<string,mixed> */
    private function deliver(array $issued, OneTimeInvitationToken $token): array
    {
        try {
            $result = $this->delivery->deliver(new OwnerInvitationDelivery(
                (int)$issued['id'],
                (int)$issued['generation'],
                (string)$issued['tenant_name'],
                (string)$issued['email'],
                (string)$issued['display_name'],
                new DateTimeImmutable((string)$issued['expires_at'], new DateTimeZone('UTC')),
                $token
            ));
        } catch (\Throwable) {
            $result = OwnerInvitationDeliveryResult::failed('delivery-port', 'DELIVERY_PROVIDER_ERROR');
        }
        $now = $this->format($this->now());
        $statement = $this->pdo->prepare(<<<'SQL'
UPDATE pa_tenant_owner_invitation
SET delivery_status = :delivery_status,
    delivery_provider = :delivery_provider,
    delivery_message_id = :delivery_message_id,
    delivery_attempts = delivery_attempts + :attempted,
    delivery_error_code = :delivery_error_code,
    last_delivery_at = CASE WHEN :attempted_at = 1 THEN :last_delivery_at ELSE last_delivery_at END,
    updated_at = :updated_at
WHERE id = :id AND token_hash = :token_hash AND status = 'pending'
SQL);
        $attempted = $result->status === 'pending_delivery' ? 0 : 1;
        $statement->execute([
            'delivery_status' => $result->status,
            'delivery_provider' => $result->provider,
            'delivery_message_id' => $result->messageId,
            'attempted' => $attempted,
            'delivery_error_code' => $result->errorCode,
            'attempted_at' => $attempted,
            'last_delivery_at' => $now,
            'updated_at' => $now,
            'id' => (int)$issued['id'],
            'token_hash' => $token->hash(),
        ]);

        $response = array_replace($issued, ['delivery_status' => $result->status]);
        if ($this->runtimePolicy->allowsPlaintextTokenResponse()) {
            $response['accept_token'] = $token->expose();
        }

        return $response;
    }

    /** @return array{id:int,tenant_id:int,email_normalized:string,display_name:string,status:string,generation:int} */
    private function lockInvitationById(int $invitationId): array
    {
        $statement = $this->pdo->prepare(<<<'SQL'
SELECT id, tenant_id, email_normalized, display_name, status, generation
FROM pa_tenant_owner_invitation
WHERE id = :id
FOR UPDATE
SQL);
        $statement->execute(['id' => $invitationId]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        if (!is_array($row)) {
            throw TenantOwnerInvitationException::notFound();
        }

        return $row;
    }

    /** @return array{id:int,code:string,name:string,status:string} */
    private function lockInvitableTenant(int $tenantId): array
    {
        $statement = $this->pdo->prepare(
            'SELECT id, code, name, status FROM pa_tenant WHERE id = :id FOR UPDATE'
        );
        $statement->execute(['id' => $tenantId]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        if (!is_array($row)) {
            throw TenantOwnerInvitationException::conflict('TENANT_NOT_FOUND', 'Tenant was not found.');
        }
        if (!in_array($row['status'], [TenantStatus::Provisioning->value, TenantStatus::Active->value], true)) {
            throw TenantOwnerInvitationException::conflict(
                'TENANT_OWNER_INVITATION_NOT_ALLOWED',
                'Owner invitations require a provisioning or active Tenant.'
            );
        }

        return $row;
    }

    private function assertOwnerBaseline(int $tenantId, string $tenantStatus): void
    {
        if ($tenantStatus === TenantStatus::Provisioning->value) {
            if ($this->memberships->pendingOrActiveMemberWithRoleExists($tenantId, self::OWNER_ROLE)) {
                throw TenantOwnerInvitationException::conflict(
                    'TENANT_OWNER_ALREADY_ASSIGNED',
                    'Tenant already has an owner candidate.'
                );
            }
            return;
        }

        if (!$this->memberships->activeMemberWithRoleExists($tenantId, self::OWNER_ROLE)) {
            throw TenantOwnerInvitationException::conflict(
                'TENANT_ACTIVE_OWNER_REQUIRED',
                'An active Tenant must retain an active owner before another owner is invited.'
            );
        }
    }

    private function expireStalePending(int $tenantId): void
    {
        $now = $this->format($this->now());
        $statement = $this->pdo->prepare(<<<'SQL'
UPDATE pa_tenant_owner_invitation
SET status = 'expired', updated_at = :updated_at
WHERE tenant_id = :tenant_id AND status = 'pending' AND expires_at <= :expired_at
SQL);
        $statement->execute(['updated_at' => $now, 'tenant_id' => $tenantId, 'expired_at' => $now]);
    }

    private function pendingInvitationExists(int $tenantId): bool
    {
        $statement = $this->pdo->prepare(
            "SELECT id FROM pa_tenant_owner_invitation WHERE tenant_id = :tenant_id AND status = 'pending' LIMIT 1"
        );
        $statement->execute(['tenant_id' => $tenantId]);
        return $statement->fetchColumn() !== false;
    }

    private function expiry(int $hours): DateTimeImmutable
    {
        if ($hours < 1 || $hours > 720) {
            throw TenantOwnerInvitationException::invalid('INVITATION_EXPIRY_INVALID');
        }
        return $this->now()->modify("+{$hours} hours");
    }

    private function now(): DateTimeImmutable
    {
        return new DateTimeImmutable('now', new DateTimeZone('UTC'));
    }

    private function format(DateTimeImmutable $dateTime): string
    {
        return $dateTime->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s.v');
    }
}
