<?php
declare(strict_types=1);

namespace app\adminapi\service;

use app\common\model\auth\Admin;
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

    public static function accessData(Admin|array $admin): array
    {
        return [
            'menu'        => self::menusForAdmin($admin),
            'permissions' => self::buttonPermissionsForAdmin($admin),
        ];
    }

    public static function menusForAdminId(int $adminId): array
    {
        $admin = Admin::with(['roles'])->findOrEmpty($adminId);
        if ($admin->isEmpty()) {
            return [];
        }
        return self::menusForAdmin($admin);
    }

    /**
     * M/C 菜单取管理员全部角色授权联集；超级管理员取全部启用菜单。
     */
    public static function menusForAdmin(Admin|array $admin): array
    {
        $query = SystemMenu::where('type', 'in', ['M', 'C'])
            ->where('is_disable', 0);

        if (!self::isRoot($admin)) {
            $menuIds = self::assignedMenuIds($admin);
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
    public static function buttonPermissionsForAdmin(Admin|array $admin): array
    {
        if (self::isRoot($admin)) {
            return ['*'];
        }

        $menuIds = self::assignedMenuIds($admin);
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
    public static function canAccess(Admin|array $admin, string $accessUri): bool
    {
        $menuIds = self::assignedMenuIds($admin);
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
            || !self::canAccess($admin, 'log/export')
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

    private static function assignedMenuIds(Admin|array $admin): array
    {
        $roleIds = self::roleIds($admin);
        if (empty($roleIds)) {
            return [];
        }

        $query = SystemRoleMenu::whereIn('role_id', $roleIds);
        $tenantId = (int)($admin instanceof Admin ? $admin->getData('tenant_id') : ($admin['tenant_id'] ?? 0));
        if ($tenantId > 0) {
            $query->where('tenant_id', $tenantId);
        }
        $menuIds = $query->column('menu_id');
        return array_values(array_unique(array_map('intval', $menuIds)));
    }

    private static function roleIds(Admin|array $admin): array
    {
        if ($admin instanceof Admin) {
            $roles = $admin->roles->toArray();
        } else {
            $roles = $admin['roles'] ?? [];
        }

        return array_values(array_unique(array_map(
            'intval',
            array_column($roles, 'id')
        )));
    }

    private static function isRoot(Admin|array $admin): bool
    {
        return (int)($admin instanceof Admin ? $admin->root : ($admin['root'] ?? 0)) === 1;
    }
}
