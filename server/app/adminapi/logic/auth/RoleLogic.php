<?php
declare(strict_types=1);

namespace app\adminapi\logic\auth;

use app\common\service\authorization\AdminAuthorizationService;
use app\common\logic\BaseLogic;
use app\common\support\PaginationInput;
use app\common\support\PositiveIds;
use PDO;
use PeanutAdmin\Kernel\Auth\TenantContext;
use PeanutAdmin\Kernel\Authorization\Application\RoleAdminService;
use think\facade\Db;

/** Compatibility role API backed by native pa_role and pa_role_permission. */
final class RoleLogic extends BaseLogic
{
    public static function validationRules(string $scene): array
    {
        self::clearError();
        return $scene === 'add'
            ? ['name' => 'require|length:1,120', 'menu_id' => 'array']
            : ['id' => 'require|integer|gt:0', 'name' => 'require|length:1,120', 'menu_id' => 'array'];
    }

    public static function lists(TenantContext $context, array $params): array
    {
        self::clearError();
        $pagination = PaginationInput::from($params);
        $result = self::service()->list($context->tenantId, $pagination->pageRequest);
        $lists = array_map(fn(array $row): array => self::compat($context, $row), $result['items']);
        return [
            'lists' => $lists,
            'count' => $result['total'],
            'pageNo' => $pagination->page,
            'pageSize' => $pagination->pageSize,
        ];
    }

    public static function getAll(TenantContext $context): array
    {
        self::clearError();
        return self::lists($context, ['page_size' => 100])['lists'];
    }

    public static function detail(TenantContext $context, int $id): array
    {
        self::clearError();
        try {
            return self::compat($context, self::service()->get($context->tenantId, $id));
        } catch (\Throwable) {
            return [];
        }
    }

    public static function add(TenantContext $context, array $params): bool
    {
        self::clearError();
        try {
            $service = self::service();
            $role = $service->create($context->tenantId, 'application.admin.' . bin2hex(random_bytes(8)),
                (string)$params['name'], (string)($params['desc'] ?? ''), $context->memberId, $context->accountId, $context->requestId);
            $keys = self::permissionKeys($context->tenantId, self::menuIds($params));
            if ($keys !== []) {
                $service->replacePermissions($context->tenantId, (int)$role['id'], $keys, (int)$role['revision'],
                    $context->memberId, $context->accountId, $context->requestId);
            }
            return true;
        } catch (\Throwable $e) {
            return self::fail($e);
        }
    }

    public static function edit(TenantContext $context, array $params): bool
    {
        self::clearError();
        try {
            $service = self::service();
            $current = $service->get($context->tenantId, (int)$params['id']);
            $role = $service->update($context->tenantId, (int)$params['id'], (string)$params['name'],
                (string)($params['desc'] ?? ''), (int)$current['revision'], $context->memberId, $context->accountId, $context->requestId);
            if (array_key_exists('menu_id', $params) || array_key_exists('menu_ids', $params)) {
                $service->replacePermissions($context->tenantId, (int)$params['id'], self::permissionKeys($context->tenantId, self::menuIds($params)),
                    (int)$role['revision'], $context->memberId, $context->accountId, $context->requestId);
            }
            return true;
        } catch (\Throwable $e) {
            return self::fail($e);
        }
    }

    public static function delete(TenantContext $context, int $id): bool
    {
        self::clearError();
        try {
            $service = self::service();
            $role = $service->get($context->tenantId, $id);
            $service->archive($context->tenantId, $id, (int)$role['revision'], $context->memberId, $context->accountId, $context->requestId);
            return true;
        } catch (\Throwable $e) {
            return self::fail($e);
        }
    }

    private static function compat(TenantContext $context, array $role): array
    {
        $keys = $role['permission_keys'] ?? [];
        $menus = [];
        if ($keys !== []) {
            $placeholders = implode(',', array_fill(0, count($keys), '?'));
            $statement = self::pdo()->prepare("SELECT id FROM pa_system_menu WHERE is_disable=0 AND perms IN ({$placeholders}) ORDER BY id");
            $statement->execute($keys);
            $menus = array_map('intval', $statement->fetchAll(PDO::FETCH_COLUMN));
            foreach ((new AdminAuthorizationService(self::pdo()))->assignableMenuRecords($context) as $menu) {
                if (in_array((string)$menu['required_permission'], $keys, true)) {
                    $menus[] = (int)$menu['id'];
                }
            }
        }
        return ['id' => (int)$role['id'], 'name' => $role['name'], 'desc' => $role['description'] ?? '', 'sort' => 0,
            'create_time' => '', 'num' => self::memberCount($context->tenantId, (int)$role['id']),
            'menu_id' => $menus, 'menu_ids' => $menus, 'status' => $role['status'], 'revision' => (int)$role['revision']];
    }

    private static function memberCount(int $tenantId, int $roleId): int
    {
        $statement = self::pdo()->prepare('SELECT COUNT(*) FROM pa_member_role WHERE tenant_id=? AND role_id=?');
        $statement->execute([$tenantId, $roleId]);
        return (int)$statement->fetchColumn();
    }

    /** @return list<int> */
    private static function menuIds(array $params): array
    {
        $ids = $params['menu_id'] ?? $params['menu_ids'] ?? [];
        return PositiveIds::normalize(
            is_array($ids) ? $ids : [],
            [PositiveIds::FILTER_INVALID],
        );
    }

    /** @param list<int> $menuIds @return list<string> */
    private static function permissionKeys(int $tenantId, array $menuIds): array
    {
        if ($menuIds === []) return [];
        $placeholders = implode(',', array_fill(0, count($menuIds), '?'));
        $statement = self::pdo()->prepare("SELECT DISTINCT p.`key` FROM pa_system_menu m JOIN pa_permission p ON p.`key`=m.perms AND p.status='active' WHERE m.id IN ({$placeholders}) AND m.is_disable=0 AND m.perms<>'' ORDER BY p.`key`");
        $statement->execute($menuIds);
        $keys = array_values(array_map('strval', $statement->fetchAll(PDO::FETCH_COLUMN)));
        $selected = array_fill_keys($menuIds, true);
        foreach ((new AdminAuthorizationService(self::pdo()))->assignableMenuRecordsForTenant($tenantId) as $menu) {
            if (isset($selected[(int)$menu['id']]) && trim((string)$menu['required_permission']) !== '') {
                $keys[] = (string)$menu['required_permission'];
            }
        }
        return array_values(array_unique($keys));
    }

    private static function service(): RoleAdminService { return new RoleAdminService(self::pdo()); }
    private static function pdo(): PDO
    {
        $pdo = Db::connect()->connect();
        if (!$pdo instanceof PDO) throw new \RuntimeException('TENANT_DATABASE_CONNECTION_UNAVAILABLE');
        return $pdo;
    }
}
