<?php
declare(strict_types=1);

namespace app\adminapi\application\auth;

use app\common\service\authorization\AdminAuthorizationService;
use app\common\service\authorization\CoreTenantModuleAdminBridge;
use app\common\service\authorization\MenuPermissionUsageQuery;
use app\common\application\ApplicationService;
use app\common\model\auth\SystemMenu;
use app\common\persistence\TransactionalExecution;
use PeanutAdmin\Kernel\Platform\InstanceControlPlanePolicy;
use PeanutAdmin\Kernel\Auth\TenantContext;

class MenuApplicationService extends ApplicationService
{
    public function __construct(
        private readonly AdminAuthorizationService $authorization,
        private readonly MenuPermissionUsageQuery $permissionUsage,
        private readonly TransactionalExecution $transactions,
    ) {}

    public function getMenuByAdminId(mixed $tenantContext, int $adminId): array
    {
        self::clearError();
        return $this->authorization->menusForAdminId($tenantContext, $adminId);
    }

    public function getAll(): array
    {
        self::clearError();
        $menus = SystemMenu::whereNotIn('perms', InstanceControlPlanePolicy::tenantAdminPermissions())
            ->whereNotIn('paths', InstanceControlPlanePolicy::tenantAdminPaths())
            ->whereNotIn('paths', [
                '/article',
                '/article/cate',
                '/article/list',
                ...CoreTenantModuleAdminBridge::officialModuleMenuPaths(),
            ])
            ->order(['sort' => 'desc', 'id' => 'asc'])->select()->toArray();
        return linear_to_tree($menus);
    }

    public function getAllSimple(TenantContext $context): array
    {
        self::clearError();
        $data = SystemMenu::where('is_disable', 0)
            ->whereNotIn('perms', InstanceControlPlanePolicy::tenantAdminPermissions())
            ->whereNotIn('paths', InstanceControlPlanePolicy::tenantAdminPaths())
            ->whereNotIn('paths', [
                '/article',
                '/article/cate',
                '/article/list',
                ...CoreTenantModuleAdminBridge::officialModuleMenuPaths(),
            ])
            ->field('id,pid,name')
            ->order(['sort' => 'desc', 'id' => 'asc'])->select()->toArray();
        $moduleMenus = array_map(
            static fn(array $menu): array => [
                'id' => (int)$menu['id'],
                'pid' => 0,
                'name' => (string)$menu['name'],
                'module_key' => (string)$menu['module_key'],
                'managed' => true,
            ],
            $this->authorization->assignableMenuRecords($context)
        );
        return [...linear_to_tree($data), ...$moduleMenus];
    }

    public function detail(int $id): array
    {
        self::clearError();
        return SystemMenu::findOrEmpty($id)->toArray();
    }

    public function add(array $params): bool
    {
        self::clearError();
        try {
            return (bool) $this->transactions->run(function () use ($params): bool {
                self::assertParent((int)($params['pid'] ?? 0));
                SystemMenu::create([
                    'pid' => $params['pid'] ?? 0, 'type' => $params['type'] ?? 'C',
                    'name' => $params['name'], 'icon' => $params['icon'] ?? '',
                    'sort' => $params['sort'] ?? 0, 'perms' => $params['perms'] ?? '',
                    'paths' => $params['paths'] ?? '', 'component' => $params['component'] ?? '',
                    'is_cache' => $params['is_cache'] ?? 0, 'is_show' => $params['is_show'] ?? 1,
                    'is_disable' => $params['is_disable'] ?? 0,
                ]);
                return true;
            });
        } catch (\Throwable $e) {
            return self::fail($e);
        }
    }

    public function edit(array $params): bool
    {
        self::clearError();
        try {
            return (bool) $this->transactions->run(function () use ($params): bool {
                $id = (int)$params['id'];
                $menu = SystemMenu::where('id', $id)->lock(true)->findOrEmpty();
                if ($menu->isEmpty()) throw new \RuntimeException('菜单不存在');
                self::assertParent((int)($params['pid'] ?? 0), $id);
                $menu->save([
                    'pid' => $params['pid'] ?? 0,
                    'type' => $params['type'] ?? 'C', 'name' => $params['name'],
                    'icon' => $params['icon'] ?? '', 'sort' => $params['sort'] ?? 0,
                    'perms' => $params['perms'] ?? '', 'paths' => $params['paths'] ?? '',
                    'component' => $params['component'] ?? '',
                    'is_cache' => $params['is_cache'] ?? 0, 'is_show' => $params['is_show'] ?? 1,
                    'is_disable' => $params['is_disable'] ?? 0,
                ]);
                return true;
            });
        } catch (\Throwable $e) {
            return self::fail($e);
        }
    }

    public function delete(int $id): bool
    {
        self::clearError();
        try {
            return (bool) $this->transactions->run(function () use ($id): bool {
                $menu = SystemMenu::where('id', $id)->lock(true)->findOrEmpty();
                if ($menu->isEmpty()) throw new \RuntimeException('菜单不存在');
                if (SystemMenu::where('pid', $id)->count() > 0) throw new \RuntimeException('已关联下级菜单，暂不可删除');
                $permission = trim((string)$menu->perms);
                if ($permission !== '' && self::permissionAssigned($permission)) throw new \RuntimeException('菜单已被角色使用，暂不可删除');
                $menu->delete();
                return true;
            });
        } catch (\Throwable $e) {
            return self::fail($e);
        }
    }

    public function updateStatus(int $id, int $isDisable): bool
    {
        self::clearError();
        try {
            return (bool) $this->transactions->run(function () use ($id, $isDisable): bool {
                $menu = SystemMenu::where('id', $id)->lock(true)->findOrEmpty();
                if ($menu->isEmpty()) throw new \RuntimeException('菜单不存在');
                $menu->save(['is_disable' => $isDisable]);
                return true;
            });
        } catch (\Throwable $e) {
            return self::fail($e);
        }
    }

    private static function assertParent(int $parentId, int $menuId = 0): void
    {
        if ($parentId === 0) {
            return;
        }
        if ($parentId === $menuId) {
            throw new \RuntimeException('上级菜单不可是当前菜单');
        }

        $parents = SystemMenu::lock(true)->column(['id', 'pid', 'type'], 'id');
        $visited = [];
        while ($parentId > 0) {
            if ($parentId === $menuId) {
                throw new \RuntimeException('上级菜单不可是当前菜单或其下级菜单');
            }
            if (isset($visited[$parentId])) {
                throw new \RuntimeException('菜单层级关系异常');
            }
            $visited[$parentId] = true;

            $parent = $parents[$parentId] ?? null;
            if (!is_array($parent)) {
                throw new \RuntimeException('上级菜单不存在');
            }
            if ((string)$parent['type'] === 'A') {
                throw new \RuntimeException('按钮不可作为上级菜单');
            }
            $parentId = (int)$parent['pid'];
        }
    }

    private static function permissionAssigned(string $permission): bool
    {
        return $this->permissionUsage->assigned($permission);
    }
}
