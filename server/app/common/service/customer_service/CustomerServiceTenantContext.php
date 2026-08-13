<?php
declare(strict_types=1);

namespace app\common\service\customer_service;

use PeanutAdmin\Kernel\Auth\AuthException;
use PeanutAdmin\Kernel\Auth\TenantContext;

final class CustomerServiceTenantContext
{
    public static function member(object $request): TenantContext
    {
        $context = $request->tenantContext ?? null;
        if (!$context instanceof TenantContext) {
            throw new AuthException('CONTEXT_TENANT_REQUIRED', 403);
        }
        self::tenantId($context);
        return $context;
    }

    public static function tenantId(TenantContext $context): int
    {
        if ($context->tenantId < 1
            || $context->accountId < 1
            || $context->memberId < 1
            || $context->authorizationRevision < 1
            || $context->sessionKey === ''
            || $context->clientKey === ''
            || $context->requestId === '') {
            throw new AuthException('CONTEXT_TENANT_REQUIRED', 403);
        }
        return $context->tenantId;
    }
}
