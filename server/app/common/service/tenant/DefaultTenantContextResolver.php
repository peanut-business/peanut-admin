<?php
declare(strict_types=1);

namespace app\common\service\tenant;

use PeanutAdmin\Kernel\Context\TenantSystemContext;
use think\facade\Db;

/** Resolves anonymous application work only through the unique active default Tenant. */
final class DefaultTenantContextResolver
{
    public static function system(string $actor, string $operation, string $operationId): TenantSystemContext
    {
        $actor = trim($actor);
        $operation = trim($operation);
        $operationId = trim($operationId);
        if ($actor === '' || $operation === '' || $operationId === '') {
            throw new \DomainException('DEFAULT_TENANT_CONTEXT_UNAVAILABLE');
        }

        $tenantIds = Db::name('tenant')
            ->where('code', 'default')
            ->where('status', 'active')
            ->limit(2)
            ->column('id');
        if (count($tenantIds) !== 1 || (int)$tenantIds[0] < 1) {
            throw new \DomainException('DEFAULT_TENANT_CONTEXT_UNAVAILABLE');
        }

        return new TenantSystemContext((int)$tenantIds[0], $actor, $operation, $operationId);
    }

    public static function operationId(object $request): string
    {
        $operationId = trim((string)$request->header('X-Request-Id', ''));
        return $operationId !== '' ? $operationId : bin2hex(random_bytes(16));
    }

    private function __construct()
    {
    }
}
