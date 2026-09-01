<?php
declare(strict_types=1);

namespace app\common\service\org;

use app\common\execution\CurrentExecutionContext;
use PDO;

/** Non-ORM read boundary for the native Account/TenantMember directory. */
final readonly class AdminDirectoryQuery
{
    public function __construct(
        private PDO $pdo,
        private CurrentExecutionContext $execution,
    ) {
    }

    /** @return list<array<string,mixed>> */
    public function rows(array $filters): array
    {
        $context = $this->execution->tenantAdmin();
        $sql = <<<'SQL'
SELECT tm.id, tm.account_id, tm.display_name, tm.primary_department_id, tm.status,
       tm.created_at, tm.updated_at, a.avatar_uri, a.last_login_at,
       c.identifier_normalized AS username,
       d.name AS department_name,
       GROUP_CONCAT(DISTINCT r.id ORDER BY r.id SEPARATOR ',') AS role_ids,
       GROUP_CONCAT(DISTINCT r.name ORDER BY r.`key` SEPARATOR '/') AS role_name,
       MAX(CASE WHEN r.`key` = 'core.tenant-owner' AND r.is_builtin = 1 AND r.status = 'active' THEN 1 ELSE 0 END) AS root
FROM pa_tenant_member tm
JOIN pa_account a ON a.id = tm.account_id
JOIN pa_credential c ON c.account_id = a.id AND c.identifier_type = 'email'
LEFT JOIN pa_department d ON d.tenant_id = tm.tenant_id AND d.id = tm.primary_department_id
LEFT JOIN pa_member_role mr ON mr.tenant_id = tm.tenant_id AND mr.tenant_member_id = tm.id
LEFT JOIN pa_role r ON r.tenant_id = mr.tenant_id AND r.id = mr.role_id
WHERE tm.tenant_id = :tenant_id
SQL;
        $bindings = ['tenant_id' => $context->tenantId];
        if (!empty($filters['account'])) {
            $sql .= ' AND c.identifier_normalized LIKE :account';
            $bindings['account'] = '%' . trim((string)$filters['account']) . '%';
        }
        if (!empty($filters['name'])) {
            $sql .= ' AND tm.display_name LIKE :display_name';
            $bindings['display_name'] = '%' . trim((string)$filters['name']) . '%';
        }
        if (!empty($filters['id'])) {
            $sql .= ' AND tm.id = :member_id';
            $bindings['member_id'] = (int)$filters['id'];
        }
        if (!empty($filters['role_id'])) {
            $sql .= ' AND EXISTS (SELECT 1 FROM pa_member_role filter_mr'
                . ' WHERE filter_mr.tenant_id = tm.tenant_id'
                . ' AND filter_mr.tenant_member_id = tm.id AND filter_mr.role_id = :role_id)';
            $bindings['role_id'] = (int)$filters['role_id'];
        }
        $sql .= ' GROUP BY tm.id, tm.account_id, tm.display_name, tm.primary_department_id, tm.status,'
            . ' tm.created_at, tm.updated_at, a.avatar_uri, a.last_login_at,'
            . ' c.identifier_normalized, d.name ORDER BY tm.id DESC';

        $statement = $this->pdo->prepare($sql);
        $statement->execute($bindings);
        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    /** @return null|array{id:int,account_id:int,authorization_revision:int} */
    public function activeTenantOwner(int $tenantId, ?int $memberId, ?int $accountId): ?array
    {
        $sql = <<<'SQL'
SELECT member.id, member.account_id, member.authorization_revision
FROM pa_tenant_member member
JOIN pa_account account
  ON account.id = member.account_id
 AND account.status = 'active'
JOIN pa_member_role membership
  ON membership.tenant_id = member.tenant_id
 AND membership.tenant_member_id = member.id
JOIN pa_role role
  ON role.tenant_id = membership.tenant_id
 AND role.id = membership.role_id
 AND role.`key` = 'core.tenant-owner'
 AND role.is_builtin = 1
 AND role.status = 'active'
WHERE member.tenant_id = :tenant_id
  AND member.status = 'active'
SQL;
        $bindings = ['tenant_id' => $tenantId];
        if ($memberId !== null && $accountId !== null) {
            $sql .= "  AND member.id = :member_id\n  AND member.account_id = :account_id\n";
            $bindings['member_id'] = $memberId;
            $bindings['account_id'] = $accountId;
        }
        $statement = $this->pdo->prepare($sql . "\nORDER BY member.id ASC LIMIT 1");
        $statement->execute($bindings);
        $owner = $statement->fetch(PDO::FETCH_ASSOC);

        return is_array($owner) ? [
            'id' => (int)$owner['id'],
            'account_id' => (int)$owner['account_id'],
            'authorization_revision' => (int)$owner['authorization_revision'],
        ] : null;
    }
}
