<?php
declare(strict_types=1);

namespace app\common\service\module;

use app\common\execution\CurrentExecutionContext;
use app\common\execution\AdminExecutionContext;
use app\common\execution\ConsumerExecutionContext;
use app\common\execution\SystemExecutionContext;
use DateTimeImmutable;
use DateTimeZone;
use PDO;
use PeanutAdmin\Kernel\Auth\TenantContext;
use PeanutAdmin\Kernel\Context\AuthenticatedMemberContext;
use PeanutAdmin\Kernel\Context\TenantSystemContext;
use PeanutAdmin\Kernel\Module\ModuleExecutionContext;
use PeanutAdmin\Kernel\Module\ModuleGuard;
use PeanutAdmin\Kernel\Module\ModuleException;
use PeanutAdmin\Kernel\Module\Persistence\PdoModuleRuntimeRepository;

/**
 * Single execution boundary for every Module-aware entry point.
 *
 * Trusted identity is established before this boundary. Callers provide only
 * the Module and entry type; Tenant identity always comes from the current
 * immutable ExecutionContext.
 */
final readonly class ModuleExecutionBoundary
{
    private ModuleGuard $guard;

    public function __construct(
        private PDO $pdo,
        private CurrentExecutionContext $execution,
    ) {
        $this->guard = new ModuleGuard(new PdoModuleRuntimeRepository($this->pdo));
    }

    public function assertHttp(string $moduleKey, ?string $operation = null): void
    {
        $context = $this->moduleContext($moduleKey, $operation);
        $this->assertEnabled($context);
    }

    public function assertExternalCallback(string $moduleKey): void
    {
        $context = $this->moduleContext($moduleKey);
        $this->assertBackgroundTenant($context);
    }

    public function assertWorker(string $moduleKey): void
    {
        $context = $this->moduleContext($moduleKey);
        $this->assertBackgroundTenant($context);
    }

    public function assertScheduled(string $moduleKey): void
    {
        $context = $this->moduleContext($moduleKey);
        $this->assertBackgroundTenant($context);
    }

    private function moduleContext(string $moduleKey, ?string $operation = null): ModuleExecutionContext
    {
        $execution = $this->execution->get();
        $operation = trim((string)$operation) !== '' ? trim((string)$operation) : $execution->operation();

        return match (true) {
            $execution instanceof AdminExecutionContext => ModuleExecutionContext::admin(
                    $moduleKey,
                    $execution->tenant,
                    $operation,
                ),
            $execution instanceof ConsumerExecutionContext
                && $execution->member !== null => ModuleExecutionContext::businessMember(
                    $moduleKey,
                    $execution->member,
                    $operation,
                ),
            $execution instanceof SystemExecutionContext => ModuleExecutionContext::system(
                    $moduleKey,
                    $execution->system,
                ),
            default => throw new \DomainException('MODULE_EXECUTION_CONTEXT_REQUIRED'),
        };
    }

    private function assertEnabled(ModuleExecutionContext $context): void
    {
        $now = new DateTimeImmutable('now', new DateTimeZone('UTC'));
        $this->guard->assertDeployment($context->moduleKey);
        $this->guard->assertTenant($context->tenantId, $context->moduleKey, $now);
    }

    private function assertBackgroundTenant(ModuleExecutionContext $context): void
    {
        if (in_array($context->moduleKey, ['core', 'platform'], true)) {
            $statement = $this->pdo->prepare('SELECT status FROM pa_tenant WHERE id = :tenant_id LIMIT 1');
            $statement->execute(['tenant_id' => $context->tenantId]);
            if ($statement->fetchColumn() !== 'active') {
                throw new ModuleException('CONTEXT_TENANT_REQUIRED', 'Tenant is not active.');
            }
            return;
        }
        $this->assertEnabled($context);
    }
}
