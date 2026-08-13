<?php
declare(strict_types=1);

namespace app\common\service\oauth;

use app\common\service\member\MemberTenantContext;
use app\common\service\notice\NoticeTenantContext;
use PeanutAdmin\Kernel\Auth\AuthException;
use PeanutAdmin\Kernel\Auth\TenantContext;
use PeanutAdmin\Kernel\Context\TenantSystemContext;

final class OAuthTenantContext
{
    private const PUBLIC_OPERATIONS = [
        'member.oauth-begin',
        'member.oauth-callback',
        'member.oauth-mini-program',
    ];

    public static function tenantId(TenantContext|TenantSystemContext $context): int
    {
        if ($context instanceof TenantContext) {
            return MemberTenantContext::tenantId($context);
        }
        if ($context->tenantId > 0
            && $context->operationId !== ''
            && (($context->actorKey === MemberTenantContext::PUBLIC_AUTH_ACTOR
                    && in_array($context->operation, self::PUBLIC_OPERATIONS, true))
                || ($context->actorKey === NoticeTenantContext::VERIFICATION_ACTOR
                    && $context->operation === 'notice.verification.verify'))) {
            return $context->tenantId;
        }
        throw new AuthException('CONTEXT_TENANT_REQUIRED', 403);
    }
}
