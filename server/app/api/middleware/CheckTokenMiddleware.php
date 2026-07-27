<?php
declare(strict_types=1);

namespace app\api\middleware;

use app\api\service\UserTokenService;
use app\common\model\member\Member;
use app\common\service\JsonService;

/**
 * 用户端登录中间件
 *
 * 挂载到需要登录的路由组上；未挂载的路由无需 token。
 */
class CheckTokenMiddleware
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

        $memberId = UserTokenService::parseToken($token);
        if ($memberId === false) {
            return JsonService::fail('登录超时，请重新登录', null, 40100);
        }

        $member = Member::findOrEmpty($memberId);
        if ($member->isEmpty()) {
            return JsonService::fail('账号不存在', null, 40100);
        }
        if (!$member->status) {
            return JsonService::fail('账号已被禁用', null, 40300);
        }

        $request->memberId   = $memberId;
        $request->memberInfo = $member->toArray();

        return $next($request);
    }
}
