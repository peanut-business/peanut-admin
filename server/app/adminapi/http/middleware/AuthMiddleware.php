<?php
declare(strict_types=1);

namespace app\adminapi\http\middleware;

use app\common\model\auth\SystemMenu;
use app\common\model\auth\SystemRoleMenu;
use app\common\service\JsonService;

class AuthMiddleware
{
    public function handle($request, \Closure $next)
    {
        if ($request->controllerObject->isNotNeedLogin()) {
            return $next($request);
        }

        $adminInfo = $request->adminInfo ?? null;
        if (empty($adminInfo)) {
            return JsonService::fail('请先登录', null, 40100);
        }

        if (($adminInfo['root'] ?? 0) == 1) {
            return $next($request);
        }

        $accessUri = strtolower($request->controller() . '/' . $request->action());
        $roleIds   = array_column($adminInfo['roles'] ?? [], 'id');

        if (empty($roleIds)) {
            return JsonService::fail('暂无访问权限', null, 40300);
        }

        $menuIds   = SystemRoleMenu::whereIn('role_id', $roleIds)->column('menu_id');
        $permsList = SystemMenu::whereIn('id', $menuIds)->where('perms', '<>', '')->column('perms');
        $allowUris = array_map('strtolower', $permsList);

        if (!in_array($accessUri, $allowUris)) {
            return JsonService::fail('暂无访问权限', null, 40300);
        }

        return $next($request);
    }
}
