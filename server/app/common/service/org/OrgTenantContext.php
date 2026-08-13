<?php
declare(strict_types=1);

namespace app\common\service\org;

use PeanutAdmin\Kernel\Auth\AuthException;
use PeanutAdmin\Kernel\Auth\TenantContext;

final class OrgTenantContext
{
    public static function member(object $request): TenantContext
    {
        $context = $request->tenantContext ?? null;
        self::tenantId($context);
        return $context;
    }

    public static function tenantId(mixed $context): int
    {
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

        return $context->tenantId;
    }

    /** @param array<string,mixed> $params @return array<string,mixed> */
    public static function withoutPayloadTenant(array $params): array
    {
        unset($params['tenant_id']);
        return $params;
    }

    private function __construct()
    {
    }
}
