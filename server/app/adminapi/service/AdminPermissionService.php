<?php
declare(strict_types=1);

namespace app\adminapi\service;

use app\common\model\auth\SystemMenu;
use app\common\service\CoreServiceOverrides;
use app\common\service\platform\InstanceControlPlanePolicy;
use PeanutAdmin\ImportExport\Application\ImportExportService;
use PeanutAdmin\Kernel\Auth\TenantContext;
use PeanutAdmin\Kernel\Context\AuthorizationDecision;
use PeanutAdmin\Kernel\Context\AuthorizedOperationContext;

/** Management authorization backed only by native member-role-permission grants. */
final class AdminPermissionService
{
    public static function accessData(mixed $tenantContext, array $admin): array
    {
        $native = (new CoreTenantModuleAdminBridge())->accessData($tenantContext);
        $permissions = $native['permissions'];
        return [
            'menu' => [
                ...self::compatibilityMenus($tenantContext, $admin, $permissions),
                ...$native['menu'],
            ],
            'permissions' => self::isRoot($admin)
                ? ['*']
                : array_values(array_unique($permissions)),
        ];
    }

    public static function menusForAdminId(mixed $tenantContext, int $memberId): array
    {
        if (!$tenantContext instanceof TenantContext || $tenantContext->memberId !== $memberId) {
            return [];
        }
        try {
            $admin = (new NativeAdminPrincipalRepository())->require($tenantContext);
        } catch (\Throwable) {
            return [];
        }
        return self::accessData($tenantContext, $admin)['menu'];
    }

    public static function menusForAdmin(mixed $tenantContext, array $admin): array
    {
        return self::accessData($tenantContext, $admin)['menu'];
    }

    public static function buttonPermissionsForAdmin(mixed $tenantContext, array $admin): array
    {
        return self::accessData($tenantContext, $admin)['permissions'];
    }

    public static function canAccess(mixed $tenantContext, array $admin, string $accessUri): bool
    {
        if (!self::validContext($tenantContext, $admin)) {
            return false;
        }
        if (InstanceControlPlanePolicy::isTenantAdminRoute($accessUri)) {
            return false;
        }
        $registered = SystemMenu::where('is_disable', 0)
            ->where('perms', '<>', '')
            ->whereNotIn('perms', InstanceControlPlanePolicy::tenantAdminPermissions())
            ->column('perms');
        $bridge = new CoreTenantModuleAdminBridge();
        $registered = [
            ...$registered,
            ...$bridge->registeredPermissions($tenantContext->tenantId),
        ];
        $owned = $bridge->accessData($tenantContext)['permissions'];

        return CoreServiceOverrides::adminPermissionPolicy()->canAccess(
            self::isRoot($admin),
            $accessUri,
            $registered,
            $owned,
        );
    }

    public static function authorizedAsyncExport(
        TenantContext $tenantContext,
        array $admin,
    ): AuthorizedOperationContext {
        if (!self::canAccess($tenantContext, $admin, 'log/export')) {
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

    /** @param list<string> $permissions */
    private static function compatibilityMenus(mixed $tenantContext, array $admin, array $permissions): array
    {
        if (!self::validContext($tenantContext, $admin)) {
            return [];
        }
        $query = SystemMenu::where('type', 'in', ['M', 'C'])
            ->where('is_disable', 0)
            ->whereNotIn('perms', InstanceControlPlanePolicy::tenantAdminPermissions())
            ->whereNotIn('paths', InstanceControlPlanePolicy::tenantAdminPaths());
        $query->whereNotIn('paths', ['/article', '/article/cate', '/article/list']);
        if (!self::isRoot($admin)) {
            $query->where(static function ($query) use ($permissions): void {
                $query->where('perms', '')->whereOr('perms', 'in', $permissions ?: ['__none__']);
            });
        }
        return linear_to_tree($query->order(['sort' => 'desc', 'id' => 'asc'])->select()->toArray());
    }

    private static function validContext(mixed $context, array $admin): bool
    {
        return $context instanceof TenantContext
            && $context->tenantId > 0
            && $context->accountId > 0
            && $context->memberId > 0
            && $context->authorizationRevision > 0
            && $context->sessionKey !== ''
            && $context->clientKey !== ''
            && $context->requestId !== ''
            && (int)($admin['tenant_id'] ?? 0) === $context->tenantId
            && (int)($admin['account_id'] ?? 0) === $context->accountId
            && (int)($admin['id'] ?? 0) === $context->memberId;
    }

    private static function isRoot(array $admin): bool
    {
        return (int)($admin['root'] ?? 0) === 1;
    }
}
