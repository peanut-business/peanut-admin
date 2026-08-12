<?php
declare(strict_types=1);

namespace app\platform\identity;

use PDO;
use PeanutAdmin\Kernel\Identity\EmailAddress;

/** Rejects any account that is also represented by a TenantMember. */
final readonly class PlatformOperatorAccountBoundary
{
    public function __construct(private PDO $pdo)
    {
    }

    public function assertEmailIsPlatformOnly(string $email): void
    {
        $statement = $this->pdo->prepare(<<<'SQL'
SELECT COUNT(*)
FROM pa_credential c
JOIN pa_tenant_member tm ON tm.account_id = c.account_id
WHERE c.identifier_type = 'email' AND c.identifier_normalized = :email
SQL);
        $statement->execute(['email' => EmailAddress::fromString($email)->value()]);
        $this->assertNoMembership((int)$statement->fetchColumn());
    }

    public function assertAccountIsPlatformOnly(int $accountId): void
    {
        if ($accountId <= 0) {
            throw new \DomainException('PLATFORM_OPERATOR_CONTEXT_UNTRUSTED');
        }
        $statement = $this->pdo->prepare('SELECT COUNT(*) FROM pa_tenant_member WHERE account_id = :account_id');
        $statement->execute(['account_id' => $accountId]);
        $this->assertNoMembership((int)$statement->fetchColumn());
    }

    private function assertNoMembership(int $membershipCount): void
    {
        if ($membershipCount !== 0) {
            throw new \DomainException('PLATFORM_OPERATOR_TENANT_MEMBERSHIP_FORBIDDEN');
        }
    }
}
