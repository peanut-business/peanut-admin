<?php
declare(strict_types=1);

namespace app\common\service\module;

use DateTimeImmutable;
use DateTimeZone;
use PDO;
use PeanutAdmin\Kernel\Auth\AuthException;
use PeanutAdmin\Kernel\Authorization\PdoTenantAuthorizationRepository;
use PeanutAdmin\Kernel\Module\ModuleGuard;
use PeanutAdmin\Kernel\Module\ModuleException;
use PeanutAdmin\Kernel\Module\Persistence\PdoModuleRuntimeRepository;

/** Shared Module installation, Tenant enablement and admin permission checks. */
final readonly class ModuleExecutionGuard
{
    private string $moduleKey;
    private ModuleGuard $guard;

    public function __construct(
        private PDO $pdo,
        string $moduleKey,
    ) {
        $moduleKey = trim($moduleKey);
        if (preg_match('/^[a-z0-9][a-z0-9._-]{1,127}$/D', $moduleKey) !== 1) {
            throw new ModuleException('MODULE_CONTEXT_INVALID', 'Module key is invalid.');
        }
        $this->moduleKey = $moduleKey;
        $this->guard = new ModuleGuard(new PdoModuleRuntimeRepository($this->pdo));
    }

    public function assertEnabled(ModuleExecutionContext $context): void
    {
        $this->assertModule($context);
        $now = new DateTimeImmutable('now', new DateTimeZone('UTC'));
        $this->guard->assertDeployment($this->moduleKey);
        $this->guard->assertTenant($context->tenantId, $this->moduleKey, $now);
    }

    public function assertScheduled(ModuleExecutionContext $context): void
    {
        $this->assertModule($context);
        $this->assertBackgroundTenant($context);
    }

    public function assertWorker(ModuleExecutionContext $context): void
    {
        $this->assertModule($context);
        $this->assertBackgroundTenant($context);
    }

    private function assertBackgroundTenant(ModuleExecutionContext $context): void
    {
        $now = new DateTimeImmutable('now', new DateTimeZone('UTC'));
        if (in_array($this->moduleKey, ['core', 'platform'], true)) {
            $statement = $this->pdo->prepare("SELECT status FROM pa_tenant WHERE id = :tenant_id LIMIT 1");
            $statement->execute(['tenant_id' => $context->tenantId]);
            if ($statement->fetchColumn() !== 'active') {
                throw new ModuleException('CONTEXT_TENANT_REQUIRED', 'Tenant is not active.');
            }
            return;
        }
        $this->guard->assertDeployment($this->moduleKey);
        $this->guard->assertTenant($context->tenantId, $this->moduleKey, $now);
    }

    public function assertAdminPermission(
        ModuleExecutionContext $context,
        string $permission,
        bool $rootBypass = false,
    ): void {
        $this->assertEnabled($context);
        if (!$context->isAdminMember()) {
            throw new AuthException('CONTEXT_TENANT_REQUIRED', 403);
        }
        $member = $context->subject;
        $permissions = (new PdoTenantAuthorizationRepository($this->pdo))->permissions(
            $member->tenantId,
            $member->memberId,
        );
        $this->guard->assertMemberAccess(
            $context->tenantId,
            $this->moduleKey,
            $rootBypass || $permissions->allows(trim($permission)),
            new DateTimeImmutable('now', new DateTimeZone('UTC')),
        );
    }

    private function assertModule(ModuleExecutionContext $context): void
    {
        if (!hash_equals($this->moduleKey, $context->moduleKey)) {
            throw new ModuleException('MODULE_CONTEXT_MISMATCH', 'Module execution context does not match.');
        }
    }
}
