<?php
declare(strict_types=1);

namespace app\platform\service;

use PDO;

/** Verifies that Core already provisioned the first owner and returns its member ID. */
final readonly class PdoTenantOwnerAdminProvisioner implements TenantOwnerAdminProvisioner
{
    public function __construct(private PDO $pdo)
    {
    }

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

        $statement = $this->pdo->prepare(<<<'SQL'
SELECT tm.id
FROM pa_tenant_member tm
JOIN pa_account a
  ON a.id = tm.account_id
 AND a.status = 'active'
JOIN pa_member_role mr
  ON mr.tenant_id = tm.tenant_id
 AND mr.tenant_member_id = tm.id
JOIN pa_role r
  ON r.tenant_id = mr.tenant_id
 AND r.id = mr.role_id
 AND r.id = :role_id
 AND r.`key` = 'core.tenant-owner'
 AND r.is_builtin = 1
 AND r.status = 'active'
WHERE tm.tenant_id = :tenant_id
  AND tm.id = :member_id
  AND tm.account_id = :account_id
  AND tm.status = 'active'
LIMIT 1
SQL);
        $statement->execute([
            'tenant_id' => $tenantId,
            'account_id' => $accountId,
            'member_id' => $memberId,
            'role_id' => $coreRoleId,
        ]);
        if ((int)$statement->fetchColumn() !== $memberId) {
            throw new \DomainException('TENANT_OWNER_ADMIN_PRINCIPAL_INVALID');
        }

        return $memberId;
    }
}
