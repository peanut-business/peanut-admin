<?php
declare(strict_types=1);

namespace app\common\service\permission;

use app\common\contract\AdminPermissionPolicy;
use PeanutAdmin\Kernel\Authorization\EffectivePermissionSet;

/**
 * 只有已登记的权限字符参与 RBAC；未登记 URI 按 LikeAdmin 业务规则放行。
 */
final class RegisteredAdminPermissionPolicy implements AdminPermissionPolicy
{
    public function canAccess(
        bool $isRoot,
        string $accessUri,
        iterable $registeredPermissions,
        iterable $grantedPermissions,
        array $aliases = []
    ): bool {
        if ($isRoot) {
            return true;
        }

        $normalized = strtolower(trim($accessUri, '/'));
        $normalized = $aliases[$normalized] ?? $normalized;
        $registered = new EffectivePermissionSet($this->normalize($registeredPermissions));
        if (!$registered->allows($normalized)) {
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
