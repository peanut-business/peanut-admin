<?php
declare(strict_types=1);

namespace app\common\service\file;

use app\common\service\member\AuthenticatedMemberContext;
use PeanutAdmin\Kernel\Auth\AuthException;
use PeanutAdmin\Kernel\Auth\TenantContext;

final class FileTenantContext
{
    public static function member(object $request): AuthenticatedMemberContext
    {
        $context = $request->authenticatedMemberContext ?? null;
        if (!$context instanceof AuthenticatedMemberContext) {
            throw new AuthException('CONTEXT_TENANT_REQUIRED', 403);
        }
        return $context;
    }

    public static function tenantId(AuthenticatedMemberContext|TenantContext $context): int
    {
        if ($context->tenantId < 1) {
            throw new AuthException('CONTEXT_TENANT_REQUIRED', 403);
        }
        return $context->tenantId;
    }
}
