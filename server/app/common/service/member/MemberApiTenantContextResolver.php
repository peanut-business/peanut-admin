<?php
declare(strict_types=1);

namespace app\common\service\member;

use DateTimeImmutable;
use DateTimeZone;
use PDO;
use PeanutAdmin\Kernel\Auth\TenantContext;
use PeanutAdmin\Kernel\Auth\ValidatedTenantSession;
use think\facade\Db;

/** Restores an application-member TenantContext from a verified JWT subject and authoritative ownership. */
final class MemberApiTenantContextResolver
{
    public function __construct(private ?PDO $pdo = null)
    {
    }

    public function resolve(int $memberId, string $token, string $requestId): TenantContext
    {
        if ($memberId < 1 || $token === '' || $requestId === '') {
            throw new \DomainException('MEMBER_TENANT_CONTEXT_UNAVAILABLE');
        }

        $statement = $this->connection()->prepare(<<<'SQL'
SELECT m.id AS member_id, m.tenant_id, t.authorization_revision
FROM pa_member m
JOIN pa_tenant t
  ON t.id = m.tenant_id
 AND t.status = 'active'
WHERE m.id = :member_id
  AND m.status = 1
  AND m.delete_time IS NULL
LIMIT 2
SQL);
        $statement->execute(['member_id' => $memberId]);
        $rows = $statement->fetchAll(PDO::FETCH_ASSOC);
        if (count($rows) !== 1) {
            throw new \DomainException('MEMBER_TENANT_CONTEXT_UNAVAILABLE');
        }
        $row = $rows[0];
        $tenantId = (int)($row['tenant_id'] ?? 0);
        $authorizationRevision = (int)($row['authorization_revision'] ?? 0);
        if ($tenantId < 1 || $authorizationRevision < 1) {
            throw new \DomainException('MEMBER_TENANT_CONTEXT_UNAVAILABLE');
        }

        return TenantContext::fromValidatedSession(new ValidatedTenantSession(
            $memberId,
            'member-jwt-' . hash('sha256', $token),
            $tenantId,
            $memberId,
            $memberId,
            'member-api',
            new DateTimeImmutable('now', new DateTimeZone('UTC')),
            $authorizationRevision,
        ), $requestId);
    }

    private function connection(): PDO
    {
        if ($this->pdo instanceof PDO) {
            return $this->pdo;
        }
        $connection = Db::connect()->connect();
        if (!$connection instanceof PDO) {
            throw new \RuntimeException('TENANT_DATABASE_CONNECTION_UNAVAILABLE');
        }
        return $this->pdo = $connection;
    }
}
