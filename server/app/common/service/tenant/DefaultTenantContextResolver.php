<?php
declare(strict_types=1);

namespace app\common\service\tenant;

use app\common\execution\ExecutionContextAccess;
use app\common\http\RequestTrace;
use PeanutAdmin\Kernel\Context\TenantSystemContext;
use PeanutAdmin\Kernel\Tenancy\DefaultTenantContextResolver as CoreDefaultTenantContextResolver;
use PDO;

/** Resolves anonymous application work only through the unique active default Tenant. */
final readonly class DefaultTenantContextResolver
{
    private CoreDefaultTenantContextResolver $delegate;

    public function __construct(PDO $pdo)
    {
        $this->delegate = new CoreDefaultTenantContextResolver($pdo);
    }

    public function system(string $actor, string $operation, string $operationId): TenantSystemContext
    {
        return $this->delegate->system($actor, $operation, $operationId);
    }

    public static function operationId(ExecutionContextAccess $contexts, object $request): string
    {
        return RequestTrace::id($contexts, $request, 'public');
    }
}
