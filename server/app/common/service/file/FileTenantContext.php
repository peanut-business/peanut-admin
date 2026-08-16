<?php
declare(strict_types=1);

namespace app\common\service\file;

use app\common\service\member\AuthenticatedMemberContext;
use PeanutAdmin\Kernel\Auth\AuthException;
use PeanutAdmin\Kernel\Auth\TenantContext;

final class FileTenantContext
{
    public static function member(object $request): AuthenticatedMemberContext|TenantContext
    {
        $context = $request->authenticatedMemberContext ?? null;
        if ($context instanceof AuthenticatedMemberContext) {
            return $context;
        }
        $context = $request->tenantContext ?? null;
        if ($context instanceof TenantContext && self::trustedMember($context)) {
            return $context;
        }
        throw new AuthException('CONTEXT_TENANT_REQUIRED', 403);
    }

    public static function tenantId(AuthenticatedMemberContext|TenantContext $context): int
    {
        if ($context->tenantId < 1) {
            throw new AuthException('CONTEXT_TENANT_REQUIRED', 403);
        }
        return $context->tenantId;
    }

    private static function trustedMember(TenantContext $context): bool
    {
        return $context->tenantId > 0
            && $context->accountId > 0
            && $context->memberId > 0
            && $context->authorizationRevision > 0
            && $context->sessionKey !== ''
            && $context->clientKey !== ''
            && $context->requestId !== '';
    }
}
