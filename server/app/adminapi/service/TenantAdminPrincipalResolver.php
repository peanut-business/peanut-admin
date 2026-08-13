<?php
declare(strict_types=1);

namespace app\adminapi\service;

use DomainException;
use PDO;
use PeanutAdmin\Kernel\Auth\TenantContext;

/** Resolves only the compatibility Admin mapped to a validated Core Tenant session. */
final readonly class TenantAdminPrincipalResolver
{
    public function __construct(private PDO $pdo)
    {
    }

    public function resolve(TenantContext $context): int
    {
        $statement = $this->pdo->prepare(<<<'SQL'
SELECT a.id
FROM pa_legacy_admin_tenant_map m
JOIN pa_admin a
  ON a.tenant_id = m.tenant_id
 AND a.id = m.legacy_admin_id
 AND a.disable = 0
 AND a.delete_time IS NULL
WHERE m.tenant_id = :tenant_id
  AND m.account_id = :account_id
  AND m.tenant_member_id = :member_id
LIMIT 1
SQL);
        $statement->execute([
            'tenant_id' => $context->tenantId,
            'account_id' => $context->accountId,
            'member_id' => $context->memberId,
        ]);
        $adminId = (int)$statement->fetchColumn();
        if ($adminId < 1) {
            throw new DomainException('TENANT_ADMIN_PRINCIPAL_UNAVAILABLE');
        }
        return $adminId;
    }
}
