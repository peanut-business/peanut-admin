<?php
declare(strict_types=1);

namespace app\common\service\finance;

use app\common\service\member\AuthenticatedMemberContext;
use app\common\execution\AdminExecutionContext;
use app\common\execution\ConsumerExecutionContext;
use app\common\execution\ExecutionContextAccess;
use PeanutAdmin\Kernel\Tenancy\TenantScope;
use app\common\service\external\ExternalTenantContext;
use app\common\service\external\ExternalTenantResolver;
use PeanutAdmin\Kernel\Auth\AuthException;
use PeanutAdmin\Kernel\Auth\TenantContext;
use PeanutAdmin\Kernel\Context\TenantSystemContext;

final class FinanceTenantContext
{
    public static function member(): AuthenticatedMemberContext|TenantContext
    {
        $current = ExecutionContextAccess::current();
        if ($current instanceof ConsumerExecutionContext
            && $current->member instanceof AuthenticatedMemberContext) {
            return $current->member;
        }
        if ($current instanceof AdminExecutionContext && self::trustedMember($current->tenant)) {
            return $current->tenant;
        }
        throw new AuthException('CONTEXT_TENANT_REQUIRED', 403);
    }

    public static function externalPayment(int $tenantId, string $identity): TenantSystemContext
    {
        return ExternalTenantContext::verified($tenantId, 'payment.settle', 'payment:' . hash('sha256', trim($identity)));
    }

    public static function tenantId(
        AuthenticatedMemberContext|TenantContext|TenantSystemContext|TenantScope $context
    ): int
    {
        if ($context instanceof TenantScope) {
            $tenantId = $context->tenantId();
        } elseif ($context instanceof AuthenticatedMemberContext) {
            $tenantId = $context->tenantId;
        } elseif ($context instanceof TenantContext) {
            if (!self::trustedMember($context)) {
                throw new AuthException('CONTEXT_TENANT_REQUIRED', 403);
            }
            $tenantId = $context->tenantId;
        } else {
            $tenantId = $context->tenantId;
            $external = $context->actorKey === ExternalTenantResolver::ACTOR
                && $context->operation === 'payment.settle';
            if (!$external || $context->operationId === '') {
                throw new AuthException('CONTEXT_TENANT_REQUIRED', 403);
            }
        }
        if ($tenantId < 1) {
            throw new AuthException('CONTEXT_TENANT_REQUIRED', 403);
        }
        return $tenantId;
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
