<?php
declare(strict_types=1);

namespace app\common\service\authorization;

use app\common\dto\authorization\AdminPrincipal;
use PDO;
use PeanutAdmin\Kernel\Auth\TenantContext;
use think\facade\Db;

/** Reads the management principal exclusively from the Core identity and RBAC tables. */
final class NativeAdminPrincipalRepository
{
    public function __construct(private ?PDO $pdo = null)
    {
    }

    public function require(TenantContext $context): AdminPrincipal
    {
        $statement = $this->connection()->prepare(<<<'SQL'
SELECT
    tm.id,
    tm.tenant_id,
    tm.account_id,
    tm.display_name,
    tm.primary_department_id,
    tm.status,
    tm.authorization_revision,
    tenant.name AS tenant_name,
    account.avatar_uri,
    account.last_login_at,
    credential.identifier_normalized AS username,
    (
        SELECT COUNT(*)
        FROM pa_tenant_member switch_member
        JOIN pa_tenant switch_tenant
          ON switch_tenant.id = switch_member.tenant_id
         AND switch_tenant.status = 'active'
        WHERE switch_member.account_id = tm.account_id
          AND switch_member.status = 'active'
    ) AS switchable_tenant_count,
    EXISTS (
        SELECT 1
        FROM pa_member_role owner_membership
        JOIN pa_role owner_role
          ON owner_role.tenant_id = owner_membership.tenant_id
         AND owner_role.id = owner_membership.role_id
         AND owner_role.`key` = 'core.tenant-owner'
         AND owner_role.is_builtin = 1
         AND owner_role.status = 'active'
        WHERE owner_membership.tenant_id = tm.tenant_id
          AND owner_membership.tenant_member_id = tm.id
    ) AS root
FROM pa_tenant_member tm
JOIN pa_tenant tenant
  ON tenant.id = tm.tenant_id
 AND tenant.status = 'active'
JOIN pa_account account
  ON account.id = tm.account_id
 AND account.status = 'active'
JOIN pa_credential credential
  ON credential.account_id = account.id
 AND credential.kind = 'email_password'
 AND credential.identifier_type = 'email'
 AND credential.status = 'active'
WHERE tm.tenant_id = :tenant_id
  AND tm.id = :member_id
  AND tm.account_id = :account_id
  AND tm.status = 'active'
LIMIT 1
SQL);
        $statement->execute([
            'tenant_id' => $context->tenantId,
            'member_id' => $context->memberId,
            'account_id' => $context->accountId,
        ]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        if (!is_array($row)) {
            throw new \DomainException('TENANT_ADMIN_PRINCIPAL_UNAVAILABLE');
        }

        $roles = $this->roles($context->tenantId, $context->memberId);
        return new AdminPrincipal(
            id: (int)$row['id'],
            tenantId: (int)$row['tenant_id'],
            accountId: (int)$row['account_id'],
            tenantName: (string)$row['tenant_name'],
            username: (string)$row['username'],
            nickname: (string)($row['display_name'] ?: $row['username']),
            name: (string)($row['display_name'] ?: $row['username']),
            avatar: (string)($row['avatar_uri'] ?? ''),
            root: (int)$row['root'] === 1,
            switchableTenantCount: (int)$row['switchable_tenant_count'],
            roles: $roles,
            roleName: implode('/', array_column($roles, 'name')),
            authorizationRevision: (int)$row['authorization_revision'],
            primaryDepartmentId: $row['primary_department_id'] === null
                ? null
                : (int)$row['primary_department_id'],
            lastLoginAt: $row['last_login_at'],
        );
    }

    /** @return list<array{id:int,key:string,name:string,is_builtin:bool}> */
    private function roles(int $tenantId, int $memberId): array
    {
        $statement = $this->connection()->prepare(<<<'SQL'
SELECT r.id, r.`key`, r.name, r.is_builtin
FROM pa_member_role mr
JOIN pa_role r
  ON r.tenant_id = mr.tenant_id
 AND r.id = mr.role_id
 AND r.status = 'active'
WHERE mr.tenant_id = :tenant_id
  AND mr.tenant_member_id = :member_id
ORDER BY r.`key`, r.id
SQL);
        $statement->execute(['tenant_id' => $tenantId, 'member_id' => $memberId]);
        $roles = [];
        while (($row = $statement->fetch(PDO::FETCH_ASSOC)) !== false) {
            $roles[] = [
                'id' => (int)$row['id'],
                'key' => (string)$row['key'],
                'name' => (string)$row['name'],
                'is_builtin' => (int)$row['is_builtin'] === 1,
            ];
        }
        return $roles;
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
        return $connection;
    }
}
