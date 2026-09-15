<?php
declare(strict_types=1);

namespace app\platform\services\module;

use app\platform\infrastructure\module\DeployedTenantModuleRegistry;
use app\common\contract\module\ModuleQualification;
use app\common\contract\module\ModuleQualificationQuery;
use app\common\contract\module\TenantModuleState;
use PeanutAdmin\Kernel\Module\ModuleException;
use think\facade\Db;

/** Read-only qualification projection over the verified deployment registry. */
final readonly class ModuleQualificationQueryService implements ModuleQualificationQuery
{
    public function __construct(
        private DeployedTenantModuleRegistry $registry,
    ) {
    }

    public function installedModule(string $moduleKey): ModuleQualification
    {
        $manifest = $this->registry->requireInstalled($moduleKey);
        $pluginKey = Db::name('plugin_module')->where('module_key', $moduleKey)->value('plugin_key');
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
        $keys = Db::name('module_installation')->where('status', 'active')->order('module_key')->column('module_key');
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

        $rows = Db::name('tenant_module')->where('tenant_id', $tenantId)
            ->field('id,tenant_id,module_key,status,source,config_revision,effective_at,expires_at,enabled_at,disabled_at,disabled_reason,created_at,updated_at')
            ->order('module_key')->select()->toArray();
        return array_map(
            static fn(array $row): TenantModuleState => TenantModuleState::fromRow($row),
            $rows
        );
    }

    public function activeTenantModuleKeys(int $tenantId): array
    {
        if ($tenantId < 1) {
            return [];
        }

        return array_values(array_map('strval', Db::name('tenant_module')->where('tenant_id', $tenantId)
            ->where('status', 'enabled')
            ->where(fn($query) => $query->whereNull('effective_at')->whereOr('effective_at', '<=', Db::raw('CURRENT_TIMESTAMP(3)')))
            ->where(fn($query) => $query->whereNull('expires_at')->whereOr('expires_at', '>', Db::raw('CURRENT_TIMESTAMP(3)')))
            ->order('module_key')->column('module_key')));
    }
}
