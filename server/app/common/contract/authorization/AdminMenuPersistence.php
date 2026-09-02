<?php
declare(strict_types=1);

namespace app\common\contract\authorization;

interface AdminMenuPersistence
{
    /** @param list<string> $excludedPermissions @param list<string> $excludedPaths @return list<array<string,mixed>> */
    public function administrationRecords(
        bool $enabledOnly,
        array $excludedPermissions,
        array $excludedPaths,
        bool $simple,
    ): array;

    /** @param list<string> $excludedPermissions @param list<string> $excludedPaths @param list<string> $visiblePermissions @return list<array<string,mixed>> */
    public function compatibilityRecords(
        array $excludedPermissions,
        array $excludedPaths,
        array $visiblePermissions,
    ): array;

    /** @return array<string,mixed>|null */
    public function record(int $id, bool $lock = false): ?array;

    /** @param array<string,mixed> $values */
    public function create(array $values): void;

    /** @param array<string,mixed> $values */
    public function update(int $id, array $values): void;

    public function delete(int $id): void;

    public function hasChildren(int $id): bool;

    /** @return array<int,array{id:int,pid:int,type:string}> */
    public function hierarchyRecords(): array;

    /** @param list<string> $permissionKeys @return list<int> */
    public function enabledMenuIds(array $permissionKeys): array;

    /** @param list<int> $menuIds @return list<string> */
    public function activePermissionKeys(array $menuIds): array;

    /** @return list<array{perms:string,module_key:mixed,permission_status:mixed}> */
    public function systemMenuPermissionRows(): array;
}
