<?php
declare(strict_types=1);

namespace app\adminapi\logic\dept;

use app\common\logic\BaseLogic;
use PDO;
use PeanutAdmin\Kernel\Auth\TenantContext;
use PeanutAdmin\Kernel\Authorization\Application\PageRequest;
use PeanutAdmin\Kernel\Organization\Application\DepartmentAdminService;
use think\facade\Db;

/** Compatibility department tree backed by native pa_department. */
final class DeptLogic extends BaseLogic
{
    public static function validationRules(string $scene): array
    {
        $rules = ['id' => 'require|integer|gt:0', 'name' => 'require|length:1,120',
            'pid' => 'require|integer|egt:0', 'leader' => 'max:50', 'mobile' => 'max:20',
            'sort' => 'integer|egt:0', 'status' => 'require|in:0,1'];
        if ($scene === 'add') unset($rules['id']);
        return $rules;
    }

    public static function lists(TenantContext $context, array $params = []): array
    {
        $items = self::service()->list($context->tenantId, new PageRequest(1, 100))['items'];
        $items = array_values(array_filter($items, static fn(array $row): bool =>
            (empty($params['name']) || str_contains((string)$row['name'], trim((string)$params['name'])))
            && (!isset($params['status']) || $params['status'] === '' || self::statusInt($row['status']) === (int)$params['status'])));
        return self::buildTree(array_map([self::class, 'compat'], $items));
    }

    public static function all(TenantContext $context): array
    {
        return self::lists($context, ['status' => 1]);
    }

    public static function leaderDept(TenantContext $context): array
    {
        $flat = [];
        foreach (self::service()->list($context->tenantId, new PageRequest(1, 100))['items'] as $row) {
            if ($row['status'] === 'active') $flat[] = ['id' => (int)$row['id'], 'name' => $row['name']];
        }
        return $flat;
    }

    public static function detail(TenantContext $context, int $id): array
    {
        try { return self::compat(self::service()->get($context->tenantId, $id)); }
        catch (\Throwable) { return []; }
    }

    public static function add(TenantContext $context, array $params): bool
    {
        try {
            $department = self::service()->create($context->tenantId, self::code($params), (string)$params['name'],
                (int)$params['pid'] > 0 ? (int)$params['pid'] : null, (int)($params['sort'] ?? 0),
                $context->memberId, $context->accountId, $context->requestId);
            if ((int)$params['status'] === 0) self::setStatus($context, $department, 0);
            return true;
        } catch (\Throwable $e) { self::setError($e->getMessage()); return false; }
    }

    public static function edit(TenantContext $context, array $params): bool
    {
        try {
            $service = self::service();
            $current = $service->get($context->tenantId, (int)$params['id']);
            $updated = $service->update($context->tenantId, (int)$params['id'], (string)$current['code'],
                (string)$params['name'], (int)($params['sort'] ?? 0), (int)$current['revision'],
                $context->memberId, $context->accountId, $context->requestId);
            $parent = (int)$params['pid'] > 0 ? (int)$params['pid'] : null;
            $currentParent = $updated['parent_id'] === null ? null : (int)$updated['parent_id'];
            if ($parent !== $currentParent) {
                $updated = $service->move($context->tenantId, (int)$params['id'], $parent, (int)$updated['revision'],
                    $context->memberId, $context->accountId, $context->requestId);
            }
            self::setStatus($context, $updated, (int)$params['status']);
            return true;
        } catch (\Throwable $e) { self::setError($e->getMessage()); return false; }
    }

    public static function delete(TenantContext $context, int $id): bool
    {
        try {
            $service = self::service(); $row = $service->get($context->tenantId, $id);
            $service->archive($context->tenantId, $id, (int)$row['revision'], $context->memberId, $context->accountId, $context->requestId);
            return true;
        } catch (\Throwable $e) { self::setError($e->getMessage()); return false; }
    }

    public static function updateStatus(TenantContext $context, int $id, int $status): bool
    {
        try { self::setStatus($context, self::service()->get($context->tenantId, $id), $status); return true; }
        catch (\Throwable $e) { self::setError($e->getMessage()); return false; }
    }

    private static function setStatus(TenantContext $context, array $row, int $status): void
    {
        $pdo = self::pdo();
        $target = $status === 1 ? 'active' : 'disabled';
        if ($row['status'] === $target) return;
        if ($row['status'] === 'archived') throw new \RuntimeException('部门已归档');
        $statement = $pdo->prepare("UPDATE pa_department SET status=:status,revision=revision+1,updated_at=CURRENT_TIMESTAMP(3) WHERE tenant_id=:tenant_id AND id=:id AND revision=:revision");
        $statement->execute(['status' => $target, 'tenant_id' => $context->tenantId, 'id' => (int)$row['id'], 'revision' => (int)$row['revision']]);
        if ($statement->rowCount() !== 1) throw new \RuntimeException('部门状态已被并发修改');
        $pdo->prepare('UPDATE pa_tenant SET authorization_revision=authorization_revision+1,updated_at=CURRENT_TIMESTAMP(3) WHERE id=?')->execute([$context->tenantId]);
    }

    private static function compat(array $row): array
    {
        return ['id' => (int)$row['id'], 'pid' => $row['parent_id'] === null ? 0 : (int)$row['parent_id'],
            'code' => $row['code'], 'name' => $row['name'], 'leader' => '', 'mobile' => '',
            'sort' => (int)$row['sort_order'], 'status' => self::statusInt($row['status']),
            'is_disable' => self::statusInt($row['status']) === 1 ? 0 : 1,
            'status_desc' => self::statusInt($row['status']) === 1 ? '正常' : '停用', 'revision' => (int)$row['revision']];
    }

    private static function buildTree(array $data, int $pid = 0, int $level = 0): array
    {
        $tree = [];
        foreach ($data as $item) if ($item['pid'] === $pid) { $item['level'] = $level; $item['children'] = self::buildTree($data, $item['id'], $level + 1); $tree[] = $item; }
        return $tree;
    }

    private static function statusInt(string $status): int { return $status === 'active' ? 1 : 0; }
    private static function code(array $params): string { return 'application.department.' . bin2hex(random_bytes(8)); }
    private static function service(): DepartmentAdminService { return new DepartmentAdminService(self::pdo()); }
    private static function pdo(): PDO
    {
        $pdo = Db::connect()->connect(); if (!$pdo instanceof PDO) throw new \RuntimeException('TENANT_DATABASE_CONNECTION_UNAVAILABLE'); return $pdo;
    }
}
