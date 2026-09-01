<?php
declare(strict_types=1);

namespace app\common\service\member;

use app\common\service\notice\NoticeTenantContext;
use app\common\service\external\ExternalTenantResolver;
use app\common\service\decoration\DecorationTenantContext;
use PeanutAdmin\Kernel\Auth\AuthException;
use app\common\execution\AdminExecutionContext;
use app\common\execution\ConsumerExecutionContext;
use app\common\execution\ExecutionContextAccess;
use PeanutAdmin\Kernel\Auth\TenantContext;
use PeanutAdmin\Kernel\Context\TenantSystemContext;

final class MemberTenantContext
{
    public const PUBLIC_AUTH_ACTOR = 'peanut.member.public-auth';

    public static function member(): AuthenticatedMemberContext|TenantContext
    {
        $current = ExecutionContextAccess::current();
        if ($current instanceof ConsumerExecutionContext
            && $current->member instanceof AuthenticatedMemberContext) {
            return $current->member;
        }
        if ($current instanceof AdminExecutionContext && self::trusted($current->tenant)) {
            return $current->tenant;
        }
        throw new AuthException('CONTEXT_TENANT_REQUIRED', 403);
    }

    public static function system(string $operation): TenantSystemContext
    {
        $current = ExecutionContextAccess::current();
        $context = $current instanceof ConsumerExecutionContext ? $current->publicTenant : null;
        if (!$context instanceof TenantSystemContext
            || $context->tenantId < 1
            || $context->actorKey !== self::PUBLIC_AUTH_ACTOR
            || $context->operation !== $operation
            || $context->operationId === '') {
            throw new AuthException('CONTEXT_TENANT_REQUIRED', 403);
        }
        return $context;
    }

    public static function tenantId(
        AuthenticatedMemberContext|TenantContext|TenantSystemContext $context
    ): int
    {
        if ($context instanceof AuthenticatedMemberContext) {
            return $context->tenantId;
        }
        if ($context instanceof TenantContext && self::trusted($context)) {
            return $context->tenantId;
        }
        if ($context instanceof TenantSystemContext
            && $context->tenantId > 0
            && in_array($context->actorKey, [
                self::PUBLIC_AUTH_ACTOR,
                NoticeTenantContext::VERIFICATION_ACTOR,
                DecorationTenantContext::PUBLIC_ACTOR,
            ], true)
            && $context->operation !== ''
            && $context->operationId !== '') {
            return $context->tenantId;
        }
        if ($context instanceof TenantSystemContext
            && $context->tenantId > 0
            && $context->actorKey === ExternalTenantResolver::ACTOR
            && $context->operation === 'payment.settle'
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
