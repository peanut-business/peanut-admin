<?php
declare(strict_types=1);

namespace app\common\service\finance;

use app\common\service\tenant\TenantScope;
use PeanutAdmin\Kernel\Auth\AuthException;
use PeanutAdmin\Kernel\Auth\TenantContext;
use PeanutAdmin\Kernel\Context\TenantSystemContext;

final class FinanceTenantContext
{
    public const VERIFIED_PAYMENT_ACTOR = 'peanut.finance.verified-payment';

    public static function member(object $request): TenantContext
    {
        $context = $request->tenantContext ?? null;
        if (!$context instanceof TenantContext || !self::trustedMember($context)) {
            throw new AuthException('CONTEXT_TENANT_REQUIRED', 403);
        }
        return $context;
    }

    public static function verifiedPayment(int $tenantId, string $orderSn): TenantSystemContext
    {
        $orderSn = trim($orderSn);
        if ($tenantId < 1 || $orderSn === '') {
            throw new AuthException('CONTEXT_TENANT_REQUIRED', 403);
        }
        return new TenantSystemContext(
            $tenantId,
            self::VERIFIED_PAYMENT_ACTOR,
            'finance.recharge.settle',
            'payment:' . hash('sha256', $orderSn),
        );
    }

    public static function tenantId(TenantContext|TenantSystemContext|TenantScope $context): int
    {
        if ($context instanceof TenantScope) {
            $tenantId = $context->tenantId();
        } elseif ($context instanceof TenantContext) {
            if (!self::trustedMember($context)) {
                throw new AuthException('CONTEXT_TENANT_REQUIRED', 403);
            }
            $tenantId = $context->tenantId;
        } else {
            $tenantId = $context->tenantId;
            if ($context->actorKey !== self::VERIFIED_PAYMENT_ACTOR
                || $context->operation !== 'finance.recharge.settle'
                || $context->operationId === '') {
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
