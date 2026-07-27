<?php
declare(strict_types=1);

namespace app\adminapi\http\middleware;

use app\common\model\auth\SystemMenu;
use app\common\model\auth\SystemRoleMenu;
use app\common\service\JsonService;

/**
 * 权限中间件（原生 TP 风格）
 *
 * 必须在 LoginMiddleware 之后执行（依赖 $request->adminInfo）。
 * root=1 的超级管理员放行；其余按 角色→菜单→perms 校验。
 * 访问标识由 URL path 推导（去掉 api/admin/ 前缀），例如 api/admin/menu/lists → menu/lists。
 */
class AuthMiddleware
{
    public function handle($request, \Closure $next)
    {
        $adminInfo = $request->adminInfo ?? null;
        if (empty($adminInfo)) {
            return JsonService::fail('请先登录', null, 40100);
        }

        // 超级管理员放行
        if ((int) ($adminInfo['root'] ?? 0) === 1) {
            return $next($request);
        }

        // 由请求路径推导访问标识：strip 掉入口 api/admin/ 前缀
        $path      = strtolower(trim($request->pathinfo(), '/'));
        $accessUri = preg_replace('#^api/admin/#', '', $path);

        $roleIds = array_column($adminInfo['roles'] ?? [], 'id');
        if (empty($roleIds)) {
            return JsonService::fail('暂无访问权限', null, 40300);
        }

        $menuIds   = SystemRoleMenu::whereIn('role_id', $roleIds)->column('menu_id');
        $permsList = SystemMenu::whereIn('id', $menuIds)->where('perms', '<>', '')->column('perms');
        $allowUris = array_map('strtolower', $permsList);

        if (!in_array($accessUri, $allowUris, true)) {
            return JsonService::fail('暂无访问权限', null, 40300);
        }

        return $next($request);
    }
}
