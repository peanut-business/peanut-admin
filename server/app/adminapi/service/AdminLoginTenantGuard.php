<?php
declare(strict_types=1);

namespace app\adminapi\service;

use PDO;
use think\facade\Db;

/** Rejects legacy Admin session creation unless its mapped Tenant identity is active. */
final class AdminLoginTenantGuard
{
    public function __construct(private ?PDO $pdo = null)
    {
    }

    public function assertAllowed(int $adminId): void
    {
        if ($adminId < 1) {
            throw new \DomainException('TENANT_UNAVAILABLE');
        }
        $statement = $this->connection()->prepare(<<<'SQL'
SELECT COUNT(*)
FROM pa_admin a
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
WHERE a.id = :admin_id
  AND a.disable = 0
  AND a.delete_time IS NULL
SQL);
        $statement->execute(['admin_id' => $adminId]);
        if ((int)$statement->fetchColumn() !== 1) {
            throw new \DomainException('TENANT_UNAVAILABLE');
        }
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
