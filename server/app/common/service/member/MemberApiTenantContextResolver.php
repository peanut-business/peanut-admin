<?php
declare(strict_types=1);

namespace app\common\service\member;

use PDO;
use think\facade\Db;

/** Restores application-member identity from a verified JWT subject and authoritative ownership. */
final class MemberApiTenantContextResolver
{
    public function __construct(private ?PDO $pdo = null)
    {
    }

    public function resolve(int $memberId, string $token, string $requestId): AuthenticatedMemberContext
    {
        if ($memberId < 1 || $token === '' || $requestId === '') {
            throw new \DomainException('MEMBER_TENANT_CONTEXT_UNAVAILABLE');
        }

        $statement = $this->connection()->prepare(<<<'SQL'
SELECT m.id AS member_id, m.tenant_id
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
        if ($tenantId < 1 || (int)($row['member_id'] ?? 0) !== $memberId) {
            throw new \DomainException('MEMBER_TENANT_CONTEXT_UNAVAILABLE');
        }

        return new AuthenticatedMemberContext(
            $tenantId,
            $memberId,
            hash('sha256', $token),
            $requestId,
        );
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
