<?php
declare(strict_types=1);

namespace app\common\contract;

interface AdminPermissionPolicy
{
    /**
     * @param iterable<string> $registeredPermissions
     * @param iterable<string> $grantedPermissions
     * @param array<string,string> $aliases
     */
    public function canAccess(
        bool $isRoot,
        string $accessUri,
        iterable $registeredPermissions,
        iterable $grantedPermissions,
        array $aliases = []
    ): bool;
}
