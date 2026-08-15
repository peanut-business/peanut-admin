<?php
declare(strict_types=1);

namespace app\adminapi\service;

use DomainException;
use PDO;
use PeanutAdmin\Kernel\Auth\TenantContext;

/** Resolves the native TenantMember principal represented by a validated Core session. */
final readonly class TenantAdminPrincipalResolver
{
    public function __construct(private PDO $pdo)
    {
    }

    public function resolve(TenantContext $context): int
    {
        $statement = $this->pdo->prepare(<<<'SQL'
SELECT tm.id
FROM pa_tenant_member tm
JOIN pa_tenant t ON t.id = tm.tenant_id AND t.status = 'active'
JOIN pa_account a ON a.id = tm.account_id AND a.status = 'active'
WHERE tm.tenant_id = :tenant_id
  AND tm.account_id = :account_id
  AND tm.id = :member_id
  AND tm.status = 'active'
LIMIT 1
SQL);
        $statement->execute([
            'tenant_id' => $context->tenantId,
            'account_id' => $context->accountId,
            'member_id' => $context->memberId,
        ]);
        $memberId = (int)$statement->fetchColumn();
        if ($memberId < 1) {
            throw new DomainException('TENANT_ADMIN_PRINCIPAL_UNAVAILABLE');
        }
        return $memberId;
    }
}
