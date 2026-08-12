<?php
declare(strict_types=1);

namespace app\adminapi\service;

use DateTimeImmutable;
use DateTimeZone;
use DomainException;
use PDO;
use PeanutAdmin\Kernel\Auth\TenantContext;
use PeanutAdmin\Kernel\Auth\ValidatedTenantSession;

/** Establishes a TenantContext only from an already validated legacy admin session. */
final readonly class AdminTenantContextResolver
{
    public function __construct(private PDO $pdo)
    {
    }

    /** @param array<string,mixed> $session */
    public function resolve(array $session, int $adminId, string $token, string $requestId): TenantContext
    {
        $sessionId = (int)($session['id'] ?? 0);
        if ($sessionId < 1 || $adminId < 1 || $token === '' || $requestId === '') {
            throw new DomainException('TENANT_CONTEXT_UNAVAILABLE');
        }

        $statement = $this->pdo->prepare(<<<'SQL'
SELECT
    s.id AS session_id,
    s.update_time,
    m.tenant_id,
    m.account_id,
    m.tenant_member_id,
    tm.authorization_revision
FROM pa_admin_session s
JOIN pa_admin a
  ON a.id = s.admin_id
JOIN pa_legacy_admin_tenant_map m
  ON m.legacy_admin_id = a.id
 AND m.tenant_id = a.tenant_id
JOIN pa_tenant t
  ON t.id = m.tenant_id
 AND t.status = 'active'
JOIN pa_account account
  ON account.id = m.account_id
 AND account.status = 'active'
JOIN pa_tenant_member tm
  ON tm.tenant_id = m.tenant_id
 AND tm.id = m.tenant_member_id
 AND tm.account_id = m.account_id
 AND tm.status = 'active'
WHERE s.id = :session_id
  AND s.admin_id = :admin_id
  AND s.token = :token
  AND s.expire_time > :now
  AND a.disable = 0
  AND a.delete_time IS NULL
LIMIT 1
SQL);
        $statement->execute([
            'session_id' => $sessionId,
            'admin_id' => $adminId,
            'token' => $token,
            'now' => time(),
        ]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        if (!is_array($row)) {
            throw new DomainException('TENANT_CONTEXT_UNAVAILABLE');
        }

        $issuedAt = (new DateTimeImmutable('@' . max(1, (int)$row['update_time'])))
            ->setTimezone(new DateTimeZone('UTC'));
        $validated = new ValidatedTenantSession(
            (int)$row['session_id'],
            'legacy-admin-' . hash('sha256', $token),
            (int)$row['tenant_id'],
            (int)$row['account_id'],
            (int)$row['tenant_member_id'],
            'admin-web',
            $issuedAt,
            (int)$row['authorization_revision'],
        );

        return TenantContext::fromValidatedSession($validated, $requestId);
    }
}
