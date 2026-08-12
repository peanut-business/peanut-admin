<?php
declare(strict_types=1);

namespace app\common\service\file;

use PeanutAdmin\Kernel\Auth\AuthException;
use PeanutAdmin\Kernel\Auth\TenantContext;

final class FileTenantContext
{
    public static function member(object $request): TenantContext
    {
        $context = $request->tenantContext ?? null;
        if (!$context instanceof TenantContext
            || $context->tenantId < 1
            || $context->accountId < 1
            || $context->memberId < 1
            || $context->authorizationRevision < 1
            || $context->sessionKey === ''
            || $context->clientKey === ''
            || $context->requestId === '') {
            throw new AuthException('CONTEXT_TENANT_REQUIRED', 403);
        }
        return $context;
    }

    public static function tenantId(TenantContext $context): int
    {
        if ($context->tenantId < 1) {
            throw new AuthException('CONTEXT_TENANT_REQUIRED', 403);
        }
        return $context->tenantId;
    }
}
