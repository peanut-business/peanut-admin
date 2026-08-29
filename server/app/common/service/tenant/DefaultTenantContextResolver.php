<?php
declare(strict_types=1);

namespace app\common\service\tenant;

use app\common\http\RequestTrace;
use PeanutAdmin\Kernel\Context\TenantSystemContext;
use PeanutAdmin\Kernel\Tenancy\DefaultTenantContextResolver as CoreDefaultTenantContextResolver;
use think\facade\Db;

/** Resolves anonymous application work only through the unique active default Tenant. */
final class DefaultTenantContextResolver
{
    public static function system(string $actor, string $operation, string $operationId): TenantSystemContext
    {
        $pdo = Db::connect()->connect();
        if (!$pdo instanceof \PDO) throw new \RuntimeException('TENANT_DATABASE_CONNECTION_UNAVAILABLE');
        return (new CoreDefaultTenantContextResolver($pdo))->system($actor, $operation, $operationId);
    }

    public static function operationId(object $request): string
    {
        return RequestTrace::id($request, 'public');
    }

    private function __construct()
    {
    }
}
