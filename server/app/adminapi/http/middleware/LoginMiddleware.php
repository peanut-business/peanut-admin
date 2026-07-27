<?php
declare(strict_types=1);

namespace app\adminapi\http\middleware;

use app\adminapi\service\AdminTokenService;
use app\common\model\auth\Admin;
use app\common\service\JsonService;

/**
 * 登录中间件（原生 TP 风格）
 *
 * 说明：是否需要登录由「路由是否挂载本中间件」决定，
 * 不再依赖 likeadmin 的 $request->controllerObject / notNeedLogin 约定。
 * 只要挂了本中间件，就必须持有有效 token。
 */
class LoginMiddleware
{
    public function handle($request, \Closure $next)
    {
        $authorization = $request->header('Authorization', '');
        $token         = '';
        if (str_starts_with($authorization, 'Bearer ')) {
            $token = substr($authorization, 7);
        }

        if (empty($token)) {
            return JsonService::fail('请求缺少 token', null, 40100);
        }

        $adminId = AdminTokenService::parseToken($token);
        if ($adminId === false) {
            return JsonService::fail('登录超时，请重新登录', null, 40100);
        }

        $admin = Admin::with(['roles'])->findOrEmpty($adminId);
        if ($admin->isEmpty()) {
            return JsonService::fail('账号不存在', null, 40100);
        }
        if ($admin->disable) {
            return JsonService::fail('账号已被禁用', null, 40300);
        }

        // 注入给控制器 / 后续中间件使用
        $request->adminInfo = $admin->toArray();
        $request->adminId   = $adminId;

        return $next($request);
    }
}
