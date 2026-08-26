<?php
declare(strict_types=1);

namespace app\api\middleware;

use app\api\service\UserTokenService;
use app\Modules\Official\Member\Model\Member;
use app\common\service\JsonService;
use app\common\service\member\MemberApiTenantContextResolver;
use app\common\service\member\MemberTenantContext;
use app\common\service\member\MemberTenantRepository;

/**
 * 用户端登录中间件
 *
 * 挂载到需要登录的路由组上；未挂载的路由无需 token。
 */
class CheckTokenMiddleware
{
    public function __construct(private ?MemberApiTenantContextResolver $tenantContexts = null)
    {
    }

    public function handle($request, \Closure $next)
    {
        $token = self::bearerToken((string)$request->header('Authorization', ''));

        if (empty($token)) {
            return JsonService::fail('请求缺少 token', null, 40100);
        }

        $memberId = UserTokenService::parseToken($token);
        if ($memberId === false) {
            return JsonService::fail('登录超时，请重新登录', null, 40100);
        }

        try {
            $requestId = trim((string)$request->header('X-Request-Id', ''));
            if ($requestId === '') {
                $requestId = bin2hex(random_bytes(16));
            }
            $request->authenticatedMemberContext = $this->tenantContexts()->resolve(
                $memberId,
                $token,
                $requestId,
            );
            $context = MemberTenantContext::member($request);
        } catch (\Throwable) {
            return JsonService::fail('租户上下文不可用', null, 40300);
        }
        $member = MemberTenantRepository::members($context)->where('id', $memberId)->findOrEmpty();
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

    private static function bearerToken(string $authorization): string
    {
        return preg_match(
            '/^Bearer +([A-Za-z0-9_-]+\.[A-Za-z0-9_-]+\.[A-Za-z0-9_-]+)$/iD',
            $authorization,
            $matches,
        ) === 1 ? $matches[1] : '';
    }

    private function tenantContexts(): MemberApiTenantContextResolver
    {
        return $this->tenantContexts ??= new MemberApiTenantContextResolver();
    }
}
