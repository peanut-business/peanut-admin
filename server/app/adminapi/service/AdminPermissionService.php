<?php
declare(strict_types=1);

namespace app\adminapi\service;

use app\common\model\auth\Admin;
use app\common\model\auth\AdminRole;
use app\common\model\auth\SystemMenu;
use app\common\model\auth\SystemRoleMenu;
use app\common\service\CoreServiceOverrides;
use PeanutAdmin\ImportExport\Application\ImportExportService;
use PeanutAdmin\Kernel\Auth\TenantContext;
use PeanutAdmin\Kernel\Context\AuthorizationDecision;
use PeanutAdmin\Kernel\Context\AuthorizedOperationContext;

/**
 * 管理端菜单、按钮与 API 权限的单一计算入口。
 */
class AdminPermissionService
{
    /** Peanut 的轻量状态接口复用 LikeAdmin 的管理员编辑权限。 */
    private const ACCESS_ALIASES = [
        'admin/status' => 'admin/edit',
        'dept/status' => 'dept/edit',
        'jobs/status' => 'jobs/edit',
        'menu/status' => 'menu/edit',
        'finance/account-log/lists' => 'finance.account_log/lists',
        'finance/recharge/lists' => 'recharge.recharge/lists',
        'finance/recharge/refund' => 'recharge.recharge/refund',
        'finance/recharge/refundagain' => 'recharge.recharge/refundagain',
        'finance/refund/record' => 'finance.refund/record',
        'finance/refund/log' => 'finance.refund/log',
    ];

    public static function accessData(mixed $tenantContext, Admin|array $admin): array
    {
        return [
            'menu'        => self::menusForAdmin($tenantContext, $admin),
            'permissions' => self::buttonPermissionsForAdmin($tenantContext, $admin),
        ];
    }

    public static function menusForAdminId(mixed $tenantContext, int $adminId): array
    {
        $tenantId = self::tenantId($tenantContext);
        if ($tenantId === null) {
            return [];
        }
        $admin = Admin::where('tenant_id', $tenantId)->findOrEmpty($adminId);
        if ($admin->isEmpty()) {
            return [];
        }
        return self::menusForAdmin($tenantContext, $admin);
    }

    /**
     * M/C 菜单取管理员全部角色授权联集；超级管理员取全部启用菜单。
     */
    public static function menusForAdmin(mixed $tenantContext, Admin|array $admin): array
    {
        $tenantId = self::tenantIdForAdmin($tenantContext, $admin);
        if ($tenantId === null) {
            return [];
        }
        $query = SystemMenu::where('type', 'in', ['M', 'C'])
            ->where('is_disable', 0);

        if (!self::isRoot($admin)) {
            $menuIds = self::assignedMenuIds($tenantId, $admin);
            if (empty($menuIds)) {
                return [];
            }
            $query->whereIn('id', $menuIds);
        }

        $menus = $query->order(['sort' => 'desc', 'id' => 'asc'])
            ->select()
            ->toArray();

        return linear_to_tree($menus);
    }

    /**
     * 前端按钮权限只返回已授权、已启用的 A 类型权限字符。
     */
    public static function buttonPermissionsForAdmin(mixed $tenantContext, Admin|array $admin): array
    {
        $tenantId = self::tenantIdForAdmin($tenantContext, $admin);
        if ($tenantId === null) {
            return [];
        }
        if (self::isRoot($admin)) {
            return ['*'];
        }

        $menuIds = self::assignedMenuIds($tenantId, $admin);
        if (empty($menuIds)) {
            return [];
        }

        $permissions = SystemMenu::whereIn('id', $menuIds)
            ->where('type', 'A')
            ->where('is_disable', 0)
            ->where('perms', '<>', '')
            ->column('perms');

        return array_values(array_unique($permissions));
    }

    /**
     * LikeAdmin 1.9.4 现状：只有登记在启用菜单 perms 中的 URI 才参与 RBAC；
     * 未登记 URI 直接放行，而不是默认拒绝。
     */
    public static function canAccess(mixed $tenantContext, Admin|array $admin, string $accessUri): bool
    {
        $tenantId = self::tenantIdForAdmin($tenantContext, $admin);
        if ($tenantId === null) {
            return false;
        }
        $menuIds = self::assignedMenuIds($tenantId, $admin);
        $registered = SystemMenu::where('is_disable', 0)
            ->where('perms', '<>', '')
            ->column('perms');
        $owned = empty($menuIds)
            ? []
            : SystemMenu::whereIn('id', $menuIds)
                ->where('is_disable', 0)
                ->where('perms', '<>', '')
                ->column('perms');

        return CoreServiceOverrides::adminPermissionPolicy()->canAccess(
            self::isRoot($admin),
            $accessUri,
            $registered,
            $owned,
            self::ACCESS_ALIASES
        );
    }

    /** Builds the Core authorization input only after the trusted admin boundary permits it. */
    public static function authorizedAsyncExport(
        TenantContext $tenantContext,
        Admin|array $admin,
    ): AuthorizedOperationContext {
        if ($tenantContext->tenantId < 1
            || $tenantContext->accountId < 1
            || $tenantContext->memberId < 1
            || $tenantContext->authorizationRevision < 1
            || $tenantContext->sessionKey === ''
            || $tenantContext->clientKey === ''
            || $tenantContext->requestId === ''
            || !self::canAccess($tenantContext, $admin, 'log/export')
        ) {
            throw new \DomainException('ASYNC_EXPORT_PERMISSION_DENIED');
        }

        return AuthorizedOperationContext::fromDecision(AuthorizationDecision::allow(
            $tenantContext,
            ImportExportService::RESOURCE_KEY,
            'create',
            [],
            hash('sha256', implode("\0", [
                (string)$tenantContext->tenantId,
                (string)$tenantContext->memberId,
                (string)$tenantContext->authorizationRevision,
                'log/export',
            ])),
        ));
    }

    private static function assignedMenuIds(int $tenantId, Admin|array $admin): array
    {
        $adminId = self::adminId($admin);
        if ($adminId < 1) {
            return [];
        }

        $roleIds = AdminRole::alias('ar')
            ->join('system_role r', 'r.id = ar.role_id AND r.tenant_id = ar.tenant_id')
            ->where('ar.tenant_id', $tenantId)
            ->where('ar.admin_id', $adminId)
            ->where('r.tenant_id', $tenantId)
            ->whereNull('r.delete_time')
            ->column('ar.role_id');
        $roleIds = array_values(array_unique(array_map('intval', $roleIds)));
        if (empty($roleIds)) {
            return [];
        }

        $menuIds = SystemRoleMenu::where('tenant_id', $tenantId)
            ->whereIn('role_id', $roleIds)
            ->column('menu_id');
        return array_values(array_unique(array_map('intval', $menuIds)));
    }

    private static function tenantIdForAdmin(mixed $tenantContext, Admin|array $admin): ?int
    {
        $tenantId = self::tenantId($tenantContext);
        $adminTenantId = (int)($admin instanceof Admin
            ? $admin->getData('tenant_id')
            : ($admin['tenant_id'] ?? 0));
        return $tenantId !== null && $adminTenantId === $tenantId ? $tenantId : null;
    }

    private static function tenantId(mixed $tenantContext): ?int
    {
        if (!$tenantContext instanceof TenantContext
            || $tenantContext->tenantId < 1
            || $tenantContext->accountId < 1
            || $tenantContext->memberId < 1
            || $tenantContext->authorizationRevision < 1
            || $tenantContext->sessionKey === ''
            || $tenantContext->clientKey === ''
            || $tenantContext->requestId === '') {
            return null;
        }
        return $tenantContext->tenantId;
    }

    private static function adminId(Admin|array $admin): int
    {
        return (int)($admin instanceof Admin ? $admin->getData('id') : ($admin['id'] ?? 0));
    }

    private static function isRoot(Admin|array $admin): bool
    {
        return (int)($admin instanceof Admin ? $admin->root : ($admin['root'] ?? 0)) === 1;
    }
}
