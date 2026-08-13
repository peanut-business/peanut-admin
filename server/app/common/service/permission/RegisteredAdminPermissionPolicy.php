<?php
declare(strict_types=1);

namespace app\common\service\permission;

use app\common\contract\AdminPermissionPolicy;
use PeanutAdmin\Kernel\Authorization\EffectivePermissionSet;

/**
 * Exact registered permissions only. Registration is checked before root bypass.
 */
final class RegisteredAdminPermissionPolicy implements AdminPermissionPolicy
{
    public function canAccess(
        bool $isRoot,
        string $accessUri,
        iterable $registeredPermissions,
        iterable $grantedPermissions
    ): bool {
        $normalized = strtolower(trim($accessUri, '/'));
        $registered = new EffectivePermissionSet($this->normalize($registeredPermissions));
        if (!$registered->allows($normalized)) {
            return false;
        }
        if ($isRoot) {
            return true;
        }

        $granted = new EffectivePermissionSet($this->normalize($grantedPermissions));
        return $granted->allows($normalized);
    }

    /** @param iterable<string> $permissions @return list<string> */
    private function normalize(iterable $permissions): array
    {
        $normalized = [];
        foreach ($permissions as $permission) {
            $normalized[] = strtolower((string)$permission);
        }
        return $normalized;
    }
}
