<?php
declare(strict_types=1);

namespace app\common\service\hot_search;

use PeanutAdmin\Kernel\Auth\AuthException;
use PeanutAdmin\Kernel\Auth\TenantContext;
use PeanutAdmin\Kernel\Context\TenantSystemContext;

final class HotSearchTenantContext
{
    public const PUBLIC_ACTOR = 'peanut.hot-search.public-read';
    public const PUBLIC_LIST_OPERATION = 'hot-search.lists';

    public static function member(object $request): TenantContext
    {
        $context = $request->tenantContext ?? null;
        if (!$context instanceof TenantContext || !self::trustedMember($context)) {
            throw new AuthException('CONTEXT_TENANT_REQUIRED', 403);
        }
        return $context;
    }

    public static function read(object $request): TenantContext|TenantSystemContext
    {
        $context = $request->tenantContext ?? null;
        self::tenantId($context);
        return $context;
    }

    public static function tenantId(TenantContext|TenantSystemContext|null $context): int
    {
        if ($context instanceof TenantContext && self::trustedMember($context)) {
            return $context->tenantId;
        }
        if ($context instanceof TenantSystemContext
            && $context->tenantId > 0
            && $context->actorKey === self::PUBLIC_ACTOR
            && $context->operation === self::PUBLIC_LIST_OPERATION
            && $context->operationId !== '') {
            return $context->tenantId;
        }
        throw new AuthException('CONTEXT_TENANT_REQUIRED', 403);
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
