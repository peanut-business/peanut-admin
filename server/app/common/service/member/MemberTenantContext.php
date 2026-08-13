<?php
declare(strict_types=1);

namespace app\common\service\member;

use app\common\service\notice\NoticeTenantContext;
use PeanutAdmin\Kernel\Auth\AuthException;
use PeanutAdmin\Kernel\Auth\TenantContext;
use PeanutAdmin\Kernel\Context\TenantSystemContext;

final class MemberTenantContext
{
    public const PUBLIC_AUTH_ACTOR = 'peanut.member.public-auth';

    public static function member(object $request): TenantContext
    {
        $context = $request->tenantContext ?? null;
        if (!$context instanceof TenantContext || !self::trusted($context)) {
            throw new AuthException('CONTEXT_TENANT_REQUIRED', 403);
        }
        return $context;
    }

    public static function system(object $request, string $operation): TenantSystemContext
    {
        $context = $request->tenantContext ?? null;
        if (!$context instanceof TenantSystemContext
            || $context->tenantId < 1
            || $context->actorKey !== self::PUBLIC_AUTH_ACTOR
            || $context->operation !== $operation
            || $context->operationId === '') {
            throw new AuthException('CONTEXT_TENANT_REQUIRED', 403);
        }
        return $context;
    }

    public static function tenantId(TenantContext|TenantSystemContext $context): int
    {
        if ($context instanceof TenantContext && self::trusted($context)) {
            return $context->tenantId;
        }
        if ($context instanceof TenantSystemContext
            && $context->tenantId > 0
            && in_array($context->actorKey, [self::PUBLIC_AUTH_ACTOR, NoticeTenantContext::VERIFICATION_ACTOR], true)
            && $context->operation !== ''
            && $context->operationId !== '') {
            return $context->tenantId;
        }
        throw new AuthException('CONTEXT_TENANT_REQUIRED', 403);
    }

    private static function trusted(TenantContext $context): bool
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
