<?php
declare(strict_types=1);

namespace app\common\service\tenant;

use PDO;
use app\common\service\instance\DeploymentMode;
use PeanutAdmin\Kernel\Context\TenantSystemContext;
use PeanutAdmin\Kernel\Tenancy\TenantEntryBindingResolver as CoreTenantEntryBindingResolver;
use think\facade\Config;
use think\facade\Db;

/** Resolves a request only through instance-owned Host and server-selected client bindings. */
final readonly class TenantEntryBindingResolver
{
    public const ADMIN_CLIENT = 'admin-web';
    public const MEMBER_CLIENT = 'member-api';

    public function __construct(
        PDO $pdo,
        ?\Closure $defaultSystem = null,
    ) {
        $this->delegate = new CoreTenantEntryBindingResolver($pdo, $defaultSystem);
    }

    private CoreTenantEntryBindingResolver $delegate;

    public static function production(): self
    {
        $pdo = Db::connect()->connect();
        if (!$pdo instanceof PDO) throw new \RuntimeException('TENANT_DATABASE_CONNECTION_UNAVAILABLE');
        $mode = DeploymentMode::fromConfiguredValue(Config::get('deployment.mode'));
        $allowDefault = $mode === DeploymentMode::Standalone
            && filter_var(
                Config::get('deployment.public_default_tenant_fallback', true),
                FILTER_VALIDATE_BOOL
            );

        return new self(
            $pdo,
            $allowDefault
                ? static fn (
                    string $actor,
                    string $operation,
                    string $operationId
                ): TenantSystemContext => DefaultTenantContextResolver::system(
                    $actor,
                    $operation,
                    $operationId
                )
                : null,
        );
    }

    public function loginTenantCode(
        object $request,
        string $clientKey,
        ?string $explicitTenantCode,
    ): ?string {
        return $this->delegate->loginTenantCode($request, $clientKey, $explicitTenantCode);
    }

    public function boundTenantId(object $request, string $clientKey): ?int
    {
        return $this->delegate->boundTenantId($request, $clientKey);
    }

    public function assertTenantAccess(object $request, string $clientKey, int $tenantId): void
    {
        $this->delegate->assertTenantAccess($request, $clientKey, $tenantId);
    }

    public function system(
        object $request,
        string $clientKey,
        string $actor,
        string $operation,
        string $operationId,
    ): TenantSystemContext {
        return $this->delegate->system($request, $clientKey, $actor, $operation, $operationId);
    }

    public static function normalizeHost(string $value): string
    {
        return CoreTenantEntryBindingResolver::normalizeHost($value);
    }

    public function delegate(): CoreTenantEntryBindingResolver { return $this->delegate; }
}
