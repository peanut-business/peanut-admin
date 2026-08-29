<?php
declare(strict_types=1);

namespace app\common\service\crontab;

use PeanutAdmin\Kernel\Tenancy\TenantScope;
use PeanutAdmin\Kernel\Auth\AuthException;
use PeanutAdmin\Kernel\Auth\TenantContext;
use app\common\execution\ExecutionContextAccess;

final class CrontabTenantContext
{
    public static function member(): TenantContext
    {
        $context = ExecutionContextAccess::current()?->scope;
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

    public static function tenantId(TenantContext|TenantScope $context): int
    {
        $tenantId = $context instanceof TenantContext ? $context->tenantId : $context->tenantId();
        if ($tenantId < 1) {
            throw new AuthException('CONTEXT_TENANT_REQUIRED', 403);
        }
        return $tenantId;
    }
}
