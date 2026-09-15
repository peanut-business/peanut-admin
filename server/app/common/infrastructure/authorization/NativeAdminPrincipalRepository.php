<?php
declare(strict_types=1);

namespace app\common\infrastructure\authorization;

use app\common\dto\authorization\AdminPrincipal;
use PeanutAdmin\Kernel\Auth\TenantContext;
use think\facade\Db;

/** Reads the management principal exclusively from the Core identity and RBAC tables. */
final class NativeAdminPrincipalRepository
{
    public function require(TenantContext $context): AdminPrincipal
    {
        $row = Db::name('tenant_member')->alias('member')
            ->join('tenant tenant', "tenant.id=member.tenant_id AND tenant.status='active'")
            ->join('account account', "account.id=member.account_id AND account.status='active'")
            ->join('credential credential', "credential.account_id=account.id AND credential.kind='email_password' AND credential.identifier_type='email' AND credential.status='active'")
            ->where('member.tenant_id', $context->tenantId)->where('member.id', $context->memberId)
            ->where('member.account_id', $context->accountId)->where('member.status', 'active')
            ->field('member.id,member.tenant_id,member.account_id,member.display_name,member.primary_department_id,member.status,member.authorization_revision')
            ->field('tenant.name AS tenant_name,account.avatar_uri,account.last_login_at,credential.identifier_normalized AS username')
            ->find();
        if ($row === null) {
            throw new \DomainException('TENANT_ADMIN_PRINCIPAL_UNAVAILABLE');
        }

        $roles = $this->roles($context->tenantId, $context->memberId);
        $switchableTenantCount = Db::name('tenant_member')->alias('member')
            ->join('tenant tenant', "tenant.id=member.tenant_id AND tenant.status='active'")
            ->where('member.account_id', $context->accountId)->where('member.status', 'active')->count();
        $root = false;
        foreach ($roles as $role) {
            $root = $root || ($role['key'] === 'core.tenant-owner' && $role['is_builtin']);
        }
        return new AdminPrincipal(
            id: (int)$row['id'],
            tenantId: (int)$row['tenant_id'],
            accountId: (int)$row['account_id'],
            tenantName: (string)$row['tenant_name'],
            username: (string)$row['username'],
            nickname: (string)($row['display_name'] ?: $row['username']),
            name: (string)($row['display_name'] ?: $row['username']),
            avatar: (string)($row['avatar_uri'] ?? ''),
            root: $root,
            switchableTenantCount: (int)$switchableTenantCount,
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
        $rows = Db::name('member_role')->alias('membership')
            ->join('role role', "role.tenant_id=membership.tenant_id AND role.id=membership.role_id AND role.status='active'")
            ->where('membership.tenant_id', $tenantId)->where('membership.tenant_member_id', $memberId)
            ->field('role.id,role.key,role.name,role.is_builtin')->order('role.key')->order('role.id')->select()->toArray();
        return array_map(static fn(array $row): array => [
                'id' => (int)$row['id'],
                'key' => (string)$row['key'],
                'name' => (string)$row['name'],
                'is_builtin' => (int)$row['is_builtin'] === 1,
            ], $rows);
    }
}
