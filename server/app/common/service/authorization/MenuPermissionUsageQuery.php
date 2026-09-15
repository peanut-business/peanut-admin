<?php
declare(strict_types=1);

namespace app\common\service\authorization;

use think\facade\Db;

/** Read boundary for checking whether a menu permission is assigned to a role. */
final class MenuPermissionUsageQuery
{
    public function assigned(string $permission): bool
    {
        return Db::name('role_permission')->alias('role_permission')
            ->join('permission permission', 'permission.id = role_permission.permission_id')
            ->where('permission.key', $permission)->count() > 0;
    }
}
