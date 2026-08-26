<?php
declare(strict_types=1);

namespace app\platform\service\module;

use app\common\contract\module\ModuleQualification;
use app\common\contract\module\ModuleQualificationQuery;
use app\common\contract\module\TenantModuleState;
use PDO;
use PeanutAdmin\Kernel\Module\ModuleException;

/** Read-only qualification projection over the verified deployment registry. */
final readonly class ModuleQualificationQueryService implements ModuleQualificationQuery
{
    public function __construct(
        private PDO $pdo,
        private DeployedTenantModuleRegistry $registry,
    ) {
    }

    public function installedModule(string $moduleKey): ModuleQualification
    {
        $manifest = $this->registry->requireInstalled($moduleKey);
        $statement = $this->pdo->prepare(<<<'SQL'
SELECT plugin_key
FROM pa_plugin_module
WHERE module_key = :module_key
LIMIT 1
SQL);
        $statement->execute(['module_key' => $moduleKey]);
        $pluginKey = $statement->fetchColumn();
        if (!is_string($pluginKey) || $pluginKey === '') {
            // Explicit deployment roots are allowed to register a Module without a Plugin.
            $pluginKey = 'deployment';
        }

        $dependencies = [];
        foreach (($manifest->data['dependencies'] ?? []) as $dependency) {
            if (is_array($dependency) && is_string($dependency['module_key'] ?? null)) {
                $dependencies[] = $dependency['module_key'];
            }
        }

        return new ModuleQualification(
            $moduleKey,
            $pluginKey,
            (string)$manifest->data['version'],
            (int)$manifest->data['schema_version'],
            $manifest->digest,
            array_values($dependencies),
        );
    }

    public function installedModules(): array
    {
        $statement = $this->pdo->query(<<<'SQL'
SELECT module_key
FROM pa_module_installation
WHERE status = 'active'
ORDER BY module_key ASC
SQL);
        $keys = $statement->fetchAll(PDO::FETCH_COLUMN);
        return array_map(
            fn(mixed $moduleKey): ModuleQualification => $this->installedModule((string)$moduleKey),
            $keys
        );
    }

    public function tenantModuleStates(int $tenantId): array
    {
        if ($tenantId < 1) {
            return [];
        }

        $statement = $this->pdo->prepare(<<<'SQL'
SELECT id, tenant_id, module_key, status, source, config_revision,
       effective_at, expires_at, enabled_at, disabled_at, disabled_reason,
       created_at, updated_at
FROM pa_tenant_module
WHERE tenant_id = :tenant_id
ORDER BY module_key ASC
SQL);
        $statement->execute(['tenant_id' => $tenantId]);
        return array_map(
            static fn(array $row): TenantModuleState => TenantModuleState::fromRow($row),
            $statement->fetchAll(PDO::FETCH_ASSOC)
        );
    }

    public function activeTenantModuleKeys(int $tenantId): array
    {
        if ($tenantId < 1) {
            return [];
        }

        $statement = $this->pdo->prepare(<<<'SQL'
SELECT module_key
FROM pa_tenant_module
WHERE tenant_id = :tenant_id
  AND status = 'enabled'
  AND (effective_at IS NULL OR effective_at <= CURRENT_TIMESTAMP(3))
  AND (expires_at IS NULL OR expires_at > CURRENT_TIMESTAMP(3))
ORDER BY module_key ASC
SQL);
        $statement->execute(['tenant_id' => $tenantId]);
        return array_values(array_map('strval', $statement->fetchAll(PDO::FETCH_COLUMN)));
    }
}
