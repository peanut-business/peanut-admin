<?php
declare(strict_types=1);

namespace app\common\service\authorization;

use app\common\contract\authorization\AdminMenuPersistence;
use app\common\model\auth\SystemMenu;

final class ThinkPhpAdminMenuPersistence implements AdminMenuPersistence
{
    public function administrationRecords(
        bool $enabledOnly,
        array $excludedPermissions,
        array $excludedPaths,
        bool $simple,
    ): array {
        $query = SystemMenu::where([]);
        if ($enabledOnly) {
            $query->where('is_disable', 0);
        }
        if ($excludedPermissions !== []) {
            $query->whereNotIn('perms', $excludedPermissions);
        }
        if ($excludedPaths !== []) {
            $query->whereNotIn('paths', $excludedPaths);
        }
        if ($simple) {
            $query->field(['id', 'pid', 'name']);
        }
        return $query->order(['sort' => 'desc', 'id' => 'asc'])->select()->toArray();
    }

    public function compatibilityRecords(
        array $excludedPermissions,
        array $excludedPaths,
        array $visiblePermissions,
    ): array {
        $query = SystemMenu::where('type', 'in', ['M', 'C'])->where('is_disable', 0);
        if ($excludedPermissions !== []) {
            $query->whereNotIn('perms', $excludedPermissions);
        }
        if ($excludedPaths !== []) {
            $query->whereNotIn('paths', $excludedPaths);
        }
        $query->where(static function ($query) use ($visiblePermissions): void {
            $query->where('perms', '')->whereOr('perms', 'in', $visiblePermissions ?: ['__none__']);
        });
        return $query->order(['sort' => 'desc', 'id' => 'asc'])->select()->toArray();
    }

    public function record(int $id, bool $lock = false): ?array
    {
        $query = SystemMenu::where('id', $id);
        if ($lock) {
            $query->lock(true);
        }
        $record = $query->findOrEmpty();
        return $record->isEmpty() ? null : $record->toArray();
    }

    public function create(array $values): void
    {
        SystemMenu::create($values);
    }

    public function update(int $id, array $values): void
    {
        SystemMenu::where('id', $id)->update($values);
    }

    public function delete(int $id): void
    {
        SystemMenu::where('id', $id)->delete();
    }

    public function hasChildren(int $id): bool
    {
        return SystemMenu::where('pid', $id)->count() > 0;
    }

    public function hierarchyRecords(): array
    {
        return SystemMenu::lock(true)->column(['id', 'pid', 'type'], 'id');
    }

    public function enabledMenuIds(array $permissionKeys): array
    {
        if ($permissionKeys === []) {
            return [];
        }
        return array_map('intval', SystemMenu::where('is_disable', 0)
            ->whereIn('perms', $permissionKeys)
            ->order('id')
            ->column('id'));
    }

    public function activePermissionKeys(array $menuIds): array
    {
        if ($menuIds === []) {
            return [];
        }
        return array_values(array_unique(array_map('strval', SystemMenu::alias('m')
            ->join('permission p', 'p.`key`=m.perms')
            ->where('m.is_disable', 0)
            ->whereIn('m.id', $menuIds)
            ->where('m.perms', '<>', '')
            ->where('p.status', 'active')
            ->order('p.`key`')
            ->column('p.`key`'))));
    }

    public function systemMenuPermissionRows(): array
    {
        return SystemMenu::alias('m')
            ->join('permission p', 'p.`key`=m.perms', 'LEFT')
            ->where('m.is_disable', 0)
            ->where('m.perms', '<>', '')
            ->field(['m.perms', 'p.module_key', 'p.status' => 'permission_status'])
            ->distinct(true)
            ->select()
            ->toArray();
    }
}
