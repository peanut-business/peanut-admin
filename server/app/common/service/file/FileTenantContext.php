<?php
declare(strict_types=1);

namespace app\common\service\file;

use app\common\service\member\AuthenticatedMemberContext;
use app\common\execution\AdminExecutionContext;
use app\common\execution\ConsumerExecutionContext;
use app\common\execution\ExecutionContextAccess;
use PeanutAdmin\Kernel\Auth\AuthException;
use PeanutAdmin\Kernel\Auth\TenantContext;

final class FileTenantContext
{
    public static function member(ExecutionContextAccess $contexts): AuthenticatedMemberContext|TenantContext
    {
        $current = $contexts->current();
        if ($current instanceof ConsumerExecutionContext
            && $current->member instanceof AuthenticatedMemberContext) {
            return $current->member;
        }
        if ($current instanceof AdminExecutionContext && self::trustedMember($current->tenant)) {
            return $current->tenant;
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
