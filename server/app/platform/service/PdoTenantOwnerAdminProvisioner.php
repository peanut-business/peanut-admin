<?php
declare(strict_types=1);

namespace app\platform\service;

use PDO;

/** Builds the existing Admin/RBAC principal for a newly provisioned Core Tenant owner. */
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

        $now = time();
        $role = $this->pdo->prepare(<<<'SQL'
INSERT INTO pa_system_role (tenant_id, name, `desc`, sort, create_time, update_time)
VALUES (:tenant_id, 'Tenant Owner', 'Instance-local Tenant owner', 100, :created_at, :updated_at)
SQL);
        $role->execute(['tenant_id' => $tenantId, 'created_at' => $now, 'updated_at' => $now]);
        $legacyRoleId = (int)$this->pdo->lastInsertId();

        $roleMap = $this->pdo->prepare(<<<'SQL'
INSERT INTO pa_legacy_role_tenant_map (
    tenant_id, legacy_role_id, role_id, created_at
) VALUES (:tenant_id, :legacy_role_id, :role_id, CURRENT_TIMESTAMP(3))
SQL);
        $roleMap->execute([
            'tenant_id' => $tenantId,
            'legacy_role_id' => $legacyRoleId,
            'role_id' => $coreRoleId,
        ]);

        $menus = $this->pdo->prepare(<<<'SQL'
INSERT INTO pa_system_role_menu (tenant_id, role_id, menu_id)
SELECT :tenant_id, :role_id, id FROM pa_system_menu WHERE is_disable = 0
SQL);
        $menus->execute(['tenant_id' => $tenantId, 'role_id' => $legacyRoleId]);

        $admin = $this->pdo->prepare(<<<'SQL'
INSERT INTO pa_admin (
    tenant_id, username, nickname, password, salt, avatar, root, disable,
    login_time, login_ip, multipoint_login, create_time, update_time
) VALUES (
    :tenant_id, :username, :nickname, :password, :salt, '', 0, 0,
    0, '', 1, :created_at, :updated_at
)
SQL);
        $admin->execute([
            'tenant_id' => $tenantId,
            'username' => substr('tenant-owner-' . $tenantId, 0, 50),
            'nickname' => mb_substr($displayName, 0, 50),
            // Core owns the usable credential. The compatibility principal never gets a password login secret.
            'password' => hash('sha256', random_bytes(32)),
            'salt' => bin2hex(random_bytes(8)),
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $adminId = (int)$this->pdo->lastInsertId();

        $adminRole = $this->pdo->prepare(<<<'SQL'
INSERT INTO pa_admin_role (tenant_id, admin_id, role_id)
VALUES (:tenant_id, :admin_id, :role_id)
SQL);
        $adminRole->execute(['tenant_id' => $tenantId, 'admin_id' => $adminId, 'role_id' => $legacyRoleId]);

        $adminMap = $this->pdo->prepare(<<<'SQL'
INSERT INTO pa_legacy_admin_tenant_map (
    tenant_id, legacy_admin_id, account_id, tenant_member_id, created_at
) VALUES (:tenant_id, :admin_id, :account_id, :member_id, CURRENT_TIMESTAMP(3))
SQL);
        $adminMap->execute([
            'tenant_id' => $tenantId,
            'admin_id' => $adminId,
            'account_id' => $accountId,
            'member_id' => $memberId,
        ]);

        return $adminId;
    }
}
