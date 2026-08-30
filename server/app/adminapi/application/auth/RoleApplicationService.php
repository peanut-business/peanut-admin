<?php
declare(strict_types=1);

namespace app\adminapi\application\auth;

use app\common\http\PageResult;
use app\common\service\authorization\RoleAdministrationRuntime;
use app\common\application\ApplicationService;
use app\common\support\PaginationInput;
use app\common\support\PositiveIds;
use PeanutAdmin\Kernel\Auth\TenantContext;

/** Compatibility role API backed by native pa_role and pa_role_permission. */
final class RoleApplicationService extends ApplicationService
{
    public function validationRules(string $scene): array
    {
        self::clearError();
        return $scene === 'add'
            ? ['name' => 'require|length:1,120', 'menu_id' => 'array']
            : ['id' => 'require|integer|gt:0', 'name' => 'require|length:1,120', 'menu_id' => 'array'];
    }

    public function lists(TenantContext $context, array $params): PageResult
    {
        self::clearError();
        $pagination = PaginationInput::from($params);
        $result = self::service()->list($context->tenantId, $pagination->pageRequest);
        $lists = array_map(fn(array $row): array => self::compat($context, $row), $result['items']);
        return new PageResult($lists, $result['total'], $pagination->page, $pagination->pageSize);
    }

    public function getAll(TenantContext $context): array
    {
        self::clearError();
        return self::lists($context, ['page_size' => 100])->items;
    }

    public function detail(TenantContext $context, int $id): array
    {
        self::clearError();
        try {
            return self::compat($context, self::service()->get($context->tenantId, $id));
        } catch (\Throwable) {
            return [];
        }
    }

    public function add(TenantContext $context, array $params): bool
    {
        self::clearError();
        try {
            $service = self::service();
            $role = $service->create($context->tenantId, 'application.admin.' . bin2hex(random_bytes(8)),
                (string)$params['name'], (string)($params['desc'] ?? ''), $context->memberId, $context->accountId, $context->requestId);
            $keys = self::runtime()->permissionKeys($context->tenantId, self::menuIds($params));
            if ($keys !== []) {
                $service->replacePermissions($context->tenantId, (int)$role['id'], $keys, (int)$role['revision'],
                    $context->memberId, $context->accountId, $context->requestId);
            }
            return true;
        } catch (\Throwable $e) {
            return self::fail($e);
        }
    }

    public function edit(TenantContext $context, array $params): bool
    {
        self::clearError();
        try {
            $service = self::service();
            $current = $service->get($context->tenantId, (int)$params['id']);
            $role = $service->update($context->tenantId, (int)$params['id'], (string)$params['name'],
                (string)($params['desc'] ?? ''), (int)$current['revision'], $context->memberId, $context->accountId, $context->requestId);
            if (array_key_exists('menu_id', $params) || array_key_exists('menu_ids', $params)) {
                $service->replacePermissions($context->tenantId, (int)$params['id'], self::runtime()->permissionKeys($context->tenantId, self::menuIds($params)),
                    (int)$role['revision'], $context->memberId, $context->accountId, $context->requestId);
            }
            return true;
        } catch (\Throwable $e) {
            return self::fail($e);
        }
    }

    public function delete(TenantContext $context, int $id): bool
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
        $menus = self::runtime()->menuIds($context, is_array($keys) ? $keys : []);
        return ['id' => (int)$role['id'], 'name' => $role['name'], 'desc' => $role['description'] ?? '', 'sort' => 0,
            'create_time' => '', 'num' => self::runtime()->memberCount($context->tenantId, (int)$role['id']),
            'menu_id' => $menus, 'menu_ids' => $menus, 'status' => $role['status'], 'revision' => (int)$role['revision']];
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

    private static function service(): \PeanutAdmin\Kernel\Authorization\Application\RoleAdminService
    {
        return self::runtime()->service();
    }

    private static function runtime(): RoleAdministrationRuntime
    {
        return app(RoleAdministrationRuntime::class);
    }
}
