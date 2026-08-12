<?php
declare(strict_types=1);

namespace app\adminapi\http\middleware;

use app\adminapi\http\AdminRequest;
use app\adminapi\service\AdminTenantContextResolver;
use app\adminapi\service\AdminTokenService;
use app\common\model\auth\Admin;
use app\common\service\JsonService;
use PDO;
use think\facade\Db;

/**
 * 登录中间件（原生 TP 风格）
 *
 * 说明：是否需要登录由「路由是否挂载本中间件」决定，
 * 不再依赖 likeadmin 的 $request->controllerObject / notNeedLogin 约定。
 * 只要挂了本中间件，就必须持有有效 token。
 */
class LoginMiddleware
{
    private ?AdminTenantContextResolver $tenantContexts;

    public function __construct(?AdminTenantContextResolver $tenantContexts = null)
    {
        $this->tenantContexts = $tenantContexts;
    }

    public function handle($request, \Closure $next)
    {
        $token = AdminTokenService::tokenFromRequest($request);

        if (empty($token)) {
            return JsonService::fail('请求缺少 token', null, 40100);
        }

        $session = AdminTokenService::resolveToken($token);
        if ($session === false) {
            return JsonService::fail('登录超时，请重新登录', null, 40100);
        }
        $adminId = (int)$session['admin_id'];

        $requestIp = $request->ip();
        if (($session['login_ip'] ?? '') === '') {
            if (!AdminTokenService::bindLoginIp($token, $requestIp)) {
                return JsonService::fail('ip地址发生变化，请重新登录', null, 40100);
            }
        } elseif ($session['login_ip'] !== $requestIp) {
            return JsonService::fail('ip地址发生变化，请重新登录', null, 40100);
        }

        $admin = Admin::with(['roles'])->findOrEmpty($adminId);
        if ($admin->isEmpty()) {
            return JsonService::fail('账号不存在', null, 40100);
        }
        if ($admin->disable) {
            return JsonService::fail('账号已被禁用', null, 40300);
        }

        // Request 使用重载属性，必须先完成数组装配再一次性写入。
        $adminInfo                = $admin->toArray();
        $adminInfo['token']       = $token;
        $adminInfo['terminal']    = (int)$session['terminal'];
        $adminInfo['expire_time'] = (int)$session['expire_time'];
        $request->adminInfo       = $adminInfo;
        $request->adminId         = $adminId;

        try {
            $request->tenantContext = $this->tenantContexts()->resolve(
                $session,
                $adminId,
                $token,
                AdminRequest::requestId($request),
            );
        } catch (\Throwable) {
            return JsonService::fail('租户上下文不可用', null, 40300);
        }

        return $next($request);
    }

    private function tenantContexts(): AdminTenantContextResolver
    {
        if ($this->tenantContexts !== null) {
            return $this->tenantContexts;
        }
        $pdo = Db::connect()->connect();
        if (!$pdo instanceof PDO) {
            throw new \RuntimeException('TENANT_DATABASE_CONNECTION_UNAVAILABLE');
        }

        return $this->tenantContexts = new AdminTenantContextResolver($pdo);
    }
}
