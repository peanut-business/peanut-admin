<?php
declare(strict_types=1);

namespace app\common\service\article;

use app\common\service\member\AuthenticatedMemberContext;
use app\common\execution\ExecutionContext;
use app\common\execution\ExecutionContextAccess;
use PeanutAdmin\Kernel\Auth\AuthException;
use PeanutAdmin\Kernel\Auth\TenantContext;
use PeanutAdmin\Kernel\Context\TenantSystemContext;

final class ArticleTenantContext
{
    public const PUBLIC_ACTOR = 'peanut.article.public-read';

    public static function member(): AuthenticatedMemberContext|TenantContext
    {
        $current = ExecutionContextAccess::current();
        $context = $current?->scope;
        if ($current?->actorType === ExecutionContext::MEMBER && $context instanceof AuthenticatedMemberContext) {
            return $context;
        }
        if ($current?->actorType === ExecutionContext::TENANT_ADMIN
            && $context instanceof TenantContext && self::trustedMember($context)) {
            return $context;
        }
        throw new AuthException('CONTEXT_TENANT_REQUIRED', 403);
    }

    public static function read(string $operation): TenantContext|TenantSystemContext
    {
        $current = ExecutionContextAccess::current();
        $context = $current?->scope;
        if ($context instanceof TenantContext && self::trustedMember($context)) {
            return $context;
        }
        if ($context instanceof TenantSystemContext
            && $context->tenantId > 0
            && $context->actorKey === self::PUBLIC_ACTOR
            && $context->operation === $operation
            && $context->operationId !== '') {
            return $context;
        }
        throw new AuthException('CONTEXT_TENANT_REQUIRED', 403);
    }

    public static function tenantId(
        AuthenticatedMemberContext|TenantContext|TenantSystemContext $context
    ): int
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
