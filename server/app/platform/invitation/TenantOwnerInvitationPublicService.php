<?php
declare(strict_types=1);

namespace app\platform\invitation;

use app\common\service\ApplicationPasswordPolicy;
use app\platform\service\ApplicationTenantBootstrapService;
use DateTimeImmutable;
use DateTimeZone;
use PDO;
use PeanutAdmin\Kernel\Identity\AccountStatus;
use PeanutAdmin\Kernel\Identity\CredentialStatus;
use PeanutAdmin\Kernel\Identity\EmailAddress;
use PeanutAdmin\Kernel\Membership\MembershipRepository;
use PeanutAdmin\Kernel\Membership\TenantMemberStatus;
use PeanutAdmin\Kernel\Persistence\Pdo\PdoAuditRepository;
use PeanutAdmin\Kernel\Persistence\Pdo\PdoIdentityRepository;
use PeanutAdmin\Kernel\Persistence\Pdo\PdoMembershipRepository;
use PeanutAdmin\Kernel\Persistence\Pdo\PdoTransactionManager;
use PeanutAdmin\Kernel\Tenancy\TenantStatus;

final class TenantOwnerInvitationPublicService
{
    private const OWNER_ROLE = 'core.tenant-owner';

    private PdoTransactionManager $transactions;
    private PdoIdentityRepository $identity;
    private MembershipRepository $memberships;
    private PdoAuditRepository $audit;
    private PasswordHasher $passwords;

    public function __construct(private readonly PDO $pdo)
    {
        $this->transactions = new PdoTransactionManager($pdo);
        $this->identity = new PdoIdentityRepository($pdo);
        $this->memberships = new PdoMembershipRepository($pdo);
        $this->audit = new PdoAuditRepository($pdo);
        $this->passwords = ApplicationPasswordPolicy::hasher();
    }

    /** @return array<string,mixed> */
    public function inspect(#[\SensitiveParameter] string $plaintextToken): array
    {
        $token = OneTimeInvitationToken::fromPlaintext($plaintextToken);
        $result = $this->transactions->run(function () use ($token): array {
            $invitation = $this->lockInvitation($token);
            if ($invitation['status'] === 'pending' && $this->isExpired((string)$invitation['expires_at'])) {
                $this->markExpired((int)$invitation['id']);
                $invitation['status'] = 'expired';
            }

            return $invitation;
        });

        return [
            'tenant_name' => $result['tenant_name'],
            'display_name' => $result['display_name'],
            'email_hint' => $this->maskEmail((string)$result['email_normalized']),
            'status' => $result['status'],
            'delivery_status' => $result['delivery_status'],
            'expires_at' => $result['expires_at'],
            'requires_password' => $result['status'] === 'pending'
                && !$this->credentialExists((string)$result['email_normalized']),
        ];
    }

    /** @return array<string,mixed> */
    public function accept(
        #[\SensitiveParameter] string $plaintextToken,
        #[\SensitiveParameter] ?string $newAccountPassword
    ): array {
        $token = OneTimeInvitationToken::fromPlaintext($plaintextToken);
        $result = $this->transactions->run(function () use ($token, $newAccountPassword): array {
            $invitation = $this->lockInvitation($token);
            $this->lockTenant((int)$invitation['tenant_id']);
            if ($invitation['status'] === 'accepted') {
                return ['_error' => 'INVITATION_ALREADY_ACCEPTED'];
            }
            if ($invitation['status'] === 'revoked') {
                return ['_error' => 'INVITATION_REVOKED'];
            }
            if ($invitation['status'] === 'expired'
                || $this->isExpired((string)$invitation['expires_at'])) {
                if ($invitation['status'] === 'pending') {
                    $this->markExpired((int)$invitation['id']);
                }
                return ['_error' => 'INVITATION_EXPIRED'];
            }
            if ($invitation['status'] !== 'pending') {
                return ['_error' => 'INVITATION_NOT_PENDING'];
            }
            $tenantId = (int)$invitation['tenant_id'];
            $tenantStatus = (string)$invitation['tenant_status'];
            if (!in_array($tenantStatus, [TenantStatus::Provisioning->value, TenantStatus::Active->value], true)) {
                return ['_error' => 'TENANT_OWNER_INVITATION_NOT_ALLOWED'];
            }
            if ($tenantStatus === TenantStatus::Provisioning->value) {
                if ($this->memberships->pendingOrActiveMemberWithRoleExists($tenantId, self::OWNER_ROLE)) {
                    return ['_error' => 'TENANT_OWNER_ALREADY_ASSIGNED'];
                }
            } elseif (!$this->memberships->activeMemberWithRoleExists($tenantId, self::OWNER_ROLE)) {
                return ['_error' => 'TENANT_ACTIVE_OWNER_REQUIRED'];
            }
            $role = $this->memberships->roleByKey($tenantId, self::OWNER_ROLE, true);
            if ($role === null || !$role->isBuiltin) {
                return ['_error' => 'TENANT_OWNER_ROLE_UNAVAILABLE'];
            }

            $email = EmailAddress::fromString((string)$invitation['email_normalized']);
            $credential = $this->identity->credentialByEmail($email, true);
            if ($credential === null) {
                if ($newAccountPassword === null || $newAccountPassword === '') {
                    return ['_error' => 'NEW_ACCOUNT_PASSWORD_REQUIRED'];
                }
                $account = $this->identity->createAccount((string)$invitation['display_name']);
                $credential = $this->identity->createEmailCredential(
                    $account->id,
                    $email,
                    $this->passwords->hash($newAccountPassword)
                );
            } else {
                if ($newAccountPassword !== null && $newAccountPassword !== '') {
                    return ['_error' => 'EXISTING_ACCOUNT_PASSWORD_FORBIDDEN'];
                }
                $account = $this->identity->accountById($credential->accountId, true);
                if ($account === null || $account->status !== AccountStatus::Active) {
                    return ['_error' => 'OWNER_ACCOUNT_INACTIVE'];
                }
            }
            if ($credential->status !== CredentialStatus::Active) {
                return ['_error' => 'OWNER_CREDENTIAL_INACTIVE'];
            }

            $member = $this->memberships->byTenantAndAccount($tenantId, $account->id, true);
            if ($member !== null && $this->memberships->memberHasRole($tenantId, $member->id, self::OWNER_ROLE)) {
                return ['_error' => 'ACCOUNT_ALREADY_TENANT_OWNER'];
            }
            if ($member === null) {
                $member = $this->memberships->createPending(
                    $tenantId,
                    $account->id,
                    (string)$invitation['display_name']
                );
            }
            if ($member->status === TenantMemberStatus::Pending) {
                $member = $this->memberships->transition($tenantId, $member->id, TenantMemberStatus::Active);
            } elseif ($member->status !== TenantMemberStatus::Active) {
                return ['_error' => 'TENANT_MEMBER_INACTIVE'];
            }
            $this->memberships->assignRole($tenantId, $member->id, $role->id);
            (new ApplicationTenantBootstrapService($this->pdo))->provision(
                $tenantId,
                $member->id,
                $role->id,
                (string)$invitation['tenant_code']
            );

            $now = $this->format($this->now());
            $statement = $this->pdo->prepare(<<<'SQL'
UPDATE pa_tenant_owner_invitation
SET token_hash = :consumed_token_hash, status = 'accepted', accepted_at = :accepted_at,
    accepted_account_id = :account_id, accepted_member_id = :member_id,
    updated_at = :updated_at
WHERE id = :id AND status = 'pending'
SQL);
            $statement->execute([
                'consumed_token_hash' => hash('sha256', random_bytes(32)),
                'accepted_at' => $now,
                'account_id' => $account->id,
                'member_id' => $member->id,
                'updated_at' => $now,
                'id' => (int)$invitation['id'],
            ]);
            if ($statement->rowCount() !== 1) {
                throw TenantOwnerInvitationException::conflict(
                    'INVITATION_ACCEPT_RACE',
                    'Invitation acceptance lost its concurrency guard.'
                );
            }
            $this->audit->appendTenantSystem(
                $tenantId,
                'tenant.owner-invitation.accepted',
                'platform.tenant.provision-owner',
                'owner-invitation:' . (int)$invitation['id'],
                ['invitation_id' => (int)$invitation['id'], 'member_id' => $member->id]
            );

            return [
                'invitation_id' => (int)$invitation['id'],
                'tenant_id' => $tenantId,
                'account_id' => $account->id,
                'member_id' => $member->id,
                'role_id' => $role->id,
                'status' => 'accepted',
                'tenant_status' => $tenantStatus,
            ];
        });

        if (isset($result['_error'])) {
            $this->throwAcceptanceError((string)$result['_error']);
        }

        return $result;
    }

    /** @return array<string,mixed> */
    private function lockInvitation(OneTimeInvitationToken $token): array
    {
        $statement = $this->pdo->prepare(<<<'SQL'
SELECT i.id, i.tenant_id, i.email_normalized, i.display_name, i.status,
       i.delivery_status, i.expires_at, i.accepted_account_id, i.accepted_member_id,
       t.code AS tenant_code, t.name AS tenant_name, t.status AS tenant_status
FROM pa_tenant_owner_invitation i
JOIN pa_tenant t ON t.id = i.tenant_id
WHERE i.token_hash = :token_hash
FOR UPDATE
SQL);
        $statement->execute(['token_hash' => $token->hash()]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        if (!is_array($row)) {
            throw TenantOwnerInvitationException::notFound();
        }

        return $row;
    }

    private function markExpired(int $invitationId): void
    {
        $statement = $this->pdo->prepare(<<<'SQL'
UPDATE pa_tenant_owner_invitation
SET status = 'expired', updated_at = :updated_at
WHERE id = :id AND status = 'pending'
SQL);
        $statement->execute([
            'updated_at' => $this->format($this->now()),
            'id' => $invitationId,
        ]);
    }

    /** @return array{id:int,status:string} */
    private function lockTenant(int $tenantId): array
    {
        $statement = $this->pdo->prepare(
            'SELECT id, status FROM pa_tenant WHERE id = :id FOR UPDATE'
        );
        $statement->execute(['id' => $tenantId]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        if (!is_array($row)) {
            throw TenantOwnerInvitationException::conflict('TENANT_NOT_FOUND', 'Tenant was not found.');
        }
        return $row;
    }

    private function credentialExists(string $email): bool
    {
        $statement = $this->pdo->prepare(<<<'SQL'
SELECT id
FROM pa_credential
WHERE identifier_type = 'email' AND identifier_normalized = :email
LIMIT 1
SQL);
        $statement->execute(['email' => $email]);
        return $statement->fetchColumn() !== false;
    }

    private function isExpired(string $expiresAt): bool
    {
        return new DateTimeImmutable($expiresAt, new DateTimeZone('UTC')) <= $this->now();
    }

    private function maskEmail(string $email): string
    {
        [$local, $domain] = explode('@', $email, 2);
        $visible = mb_substr($local, 0, 1);
        return $visible . str_repeat('*', max(2, mb_strlen($local) - 1)) . '@' . $domain;
    }

    /** @return never */
    private function throwAcceptanceError(string $error): void
    {
        throw match ($error) {
            'INVITATION_REVOKED' => TenantOwnerInvitationException::gone($error, 'Invitation was revoked.'),
            'INVITATION_EXPIRED' => TenantOwnerInvitationException::gone($error, 'Invitation expired.'),
            'INVITATION_ALREADY_ACCEPTED' => TenantOwnerInvitationException::gone(
                $error,
                'Invitation was already accepted.'
            ),
            'NEW_ACCOUNT_PASSWORD_REQUIRED' => TenantOwnerInvitationException::invalid(
                $error,
                'A password is required for a new Account.'
            ),
            'EXISTING_ACCOUNT_PASSWORD_FORBIDDEN' => TenantOwnerInvitationException::conflict(
                $error,
                'A password cannot be supplied for an existing Account.'
            ),
            'ACCOUNT_ALREADY_TENANT_OWNER' => TenantOwnerInvitationException::conflict(
                $error,
                'The Account already holds the Tenant owner role.'
            ),
            default => TenantOwnerInvitationException::conflict(
                $error,
                'Invitation acceptance was rejected.'
            ),
        };
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
