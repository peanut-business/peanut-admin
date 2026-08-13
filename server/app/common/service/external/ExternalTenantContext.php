<?php
declare(strict_types=1);

namespace app\common\service\external;

use PeanutAdmin\Kernel\Auth\AuthException;
use PeanutAdmin\Kernel\Auth\TenantContext;
use PeanutAdmin\Kernel\Context\TenantSystemContext;

final class ExternalTenantContext
{
    public static function verified(
        int $tenantId,
        string $operation,
        string $operationId,
    ): TenantSystemContext {
        if ($tenantId < 1 || trim($operation) === '' || trim($operationId) === '') {
            throw new AuthException('CONTEXT_TENANT_REQUIRED', 403);
        }
        return new TenantSystemContext(
            $tenantId,
            ExternalTenantResolver::ACTOR,
            trim($operation),
            trim($operationId),
        );
    }

    public static function tenantId(TenantContext|TenantSystemContext $context): int
    {
        if ($context instanceof TenantContext) {
            if ($context->tenantId < 1 || $context->accountId < 1 || $context->memberId < 1
                || $context->authorizationRevision < 1 || $context->sessionKey === ''
                || $context->clientKey === '' || $context->requestId === '') {
                throw new AuthException('CONTEXT_TENANT_REQUIRED', 403);
            }
            return $context->tenantId;
        }
        if ($context->tenantId < 1 || $context->actorKey !== ExternalTenantResolver::ACTOR
            || $context->operation === '' || $context->operationId === '') {
            throw new AuthException('CONTEXT_TENANT_REQUIRED', 403);
        }
        return $context->tenantId;
    }
}
