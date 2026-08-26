<?php
declare(strict_types=1);

namespace app\platform\service\plugin;

use PDO;
use PeanutAdmin\Kernel\Module\ManifestLoader;

/** Application service shared by Platform HTTP adapters and module:* commands. */
final readonly class PlatformModuleRuntimeService
{
    /** @param array<string,mixed> $moduleConfig @param array<string,string> $trustedPublicKeys */
    public function __construct(
        private PDO $pdo,
        private string $serverRoot,
        private array $moduleConfig,
        private array $trustedPublicKeys,
    ) {
    }

    /** @return array{items:list<array<string,mixed>>,total:int} */
    public function modules(int $page, int $pageSize, ?string $moduleKey): array
    {
        $descriptors = (new PluginLockResolver($this->serverRoot, (string)($this->moduleConfig['plugin_lock'] ?? '../plugins.lock')))->all();
        $details = [];
        $dependents = [];
        foreach ($descriptors as $descriptor) {
            foreach ($descriptor->moduleRoots as $key => $root) {
                $manifest = (new ManifestLoader())->load($root);
                $dependencies = [];
                foreach ((array)($manifest->data['dependencies'] ?? []) as $dependency) {
                    if (!is_array($dependency) || !is_string($dependency['module_key'] ?? null)) continue;
                    $dependencies[] = ['module_key' => $dependency['module_key'], 'version' => (string)($dependency['version'] ?? '')];
                    $dependents[$dependency['module_key']][] = $key;
                }
                $details[$key] = [
                    'module_key' => $key,
                    'name' => (string)($manifest->data['name'] ?? $key),
                    'version' => (string)($manifest->data['version'] ?? ''),
                    'manifest_digest' => $manifest->digest,
                    'package_key' => $descriptor->key,
                    'package_version' => $descriptor->version,
                    'dependencies' => $dependencies,
                ];
            }
        }
        $rows = $this->pdo->query(<<<'SQL'
SELECT pm.module_key,pm.module_version,pm.manifest_digest,pm.plugin_key,
       pi.installed_version package_version,pi.status package_status,
       mi.status module_status,mi.last_error_code,
       (SELECT COUNT(*) FROM pa_tenant_module tm WHERE tm.module_key=pm.module_key AND tm.status='enabled') tenant_enabled_count
FROM pa_plugin_module pm
JOIN pa_plugin_installation pi ON pi.plugin_key=pm.plugin_key
LEFT JOIN pa_module_installation mi ON mi.module_key=pm.module_key
ORDER BY pm.module_key
SQL)->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as $row) {
            $key = (string)$row['module_key'];
            $details[$key] ??= [
                'module_key' => $key,
                'name' => $key,
                'version' => (string)$row['module_version'],
                'manifest_digest' => (string)$row['manifest_digest'],
                'package_key' => (string)$row['plugin_key'],
                'package_version' => (string)$row['package_version'],
                'dependencies' => [],
            ];
            $details[$key]['status'] = $row['module_status'] ?? ($row['package_status'] === 'uninstalled' ? 'clean' : $row['package_status']);
            $details[$key]['tenant_enabled_count'] = (int)$row['tenant_enabled_count'];
            $details[$key]['blockers'] = $row['last_error_code'] === null ? [] : [(string)$row['last_error_code']];
        }
        foreach ($details as $key => &$detail) {
            $detail['status'] ??= 'locked';
            $detail['tenant_enabled_count'] ??= 0;
            $detail['blockers'] ??= [];
            $detail['dependents'] = array_values(array_unique($dependents[$key] ?? []));
            sort($detail['dependents'], SORT_STRING);
        }
        unset($detail);
        ksort($details, SORT_STRING);
        if ($moduleKey !== null) $details = isset($details[$moduleKey]) ? [$moduleKey => $details[$moduleKey]] : [];
        $total = count($details);
        return ['items' => array_slice(array_values($details), ($page - 1) * $pageSize, $pageSize), 'total' => $total];
    }

    /** @return array<string,mixed> */
    public function install(string $archivePath, string $expectedSha256, ?string $signatureKeyId): array
    {
        $result = (new PluginPackageInstaller($this->pdo, $this->serverRoot, $this->moduleConfig, $this->trustedPublicKeys))
            ->install($archivePath, $expectedSha256, $signatureKeyId);
        $moduleKeys = array_values(array_map(static fn(array $module): string => (string)$module['module_key'], $result['modules'] ?? []));
        $catalog = $this->catalog();
        $operation = ($result['operation'] ?? null) === 'unchanged'
            ? 'unchanged'
            : (($result['operation'] ?? null) === 'installed' ? 'installed' : 'reactivated');
        if ($operation !== 'unchanged') $catalog->invalidateTenantAuthorization($moduleKeys);
        $result['operation'] = $operation;
        $result['catalog_revision'] = $catalog->catalogRevision();
        return $result;
    }

    /** @return array<string,mixed> */
    public function uninstallPreview(string $key, bool $purge): array
    {
        return (new PluginRuntimeGovernanceService($this->pdo, $this->serverRoot, $this->moduleConfig))->preview($key, $purge);
    }

    /** @param array<string,mixed> $plan @return array<string,mixed> */
    public function uninstall(string $key, bool $purge, array $plan, string $digest): array
    {
        $result = (new PluginRuntimeGovernanceService($this->pdo, $this->serverRoot, $this->moduleConfig))->uninstall($key, $purge, $plan, $digest);
        $moduleKeys = array_values(array_map(static fn(array $module): string => (string)$module['module_key'], $result['affected_modules'] ?? []));
        $catalog = $this->catalog();
        $catalog->invalidateTenantAuthorization($moduleKeys);
        return $result + ['catalog_revision' => $catalog->catalogRevision()];
    }

    /** @return array<string,mixed> */
    public function disable(string $moduleKey): array
    {
        $statement = $this->pdo->prepare('SELECT status FROM pa_module_installation WHERE module_key=?');
        $statement->execute([$moduleKey]);
        $status = $statement->fetchColumn();
        if ($status === 'maintenance') return ['operation' => 'unchanged', 'module_key' => $moduleKey, 'status' => 'maintenance', 'catalog_revision' => $this->catalog()->catalogRevision()];
        if ($status !== 'active') throw new PluginLifecycleException('MODULE_STATE_INVALID', 'Only an active Module can be disabled.');
        $projection = $this->modules(1, 10000, $moduleKey)['items'][0] ?? null;
        foreach ((array)($projection['dependents'] ?? []) as $dependent) {
            $active = $this->pdo->prepare("SELECT COUNT(*) FROM pa_module_installation WHERE module_key=? AND status='active'");
            $active->execute([$dependent]);
            if ((int)$active->fetchColumn() !== 0) throw new PluginLifecycleException('MODULE_DEPENDENT_INSTALLED', 'An active Module depends on the target Module.');
        }
        $enabled = $this->pdo->prepare("SELECT COUNT(*) FROM pa_tenant_module WHERE module_key=? AND status='enabled'");
        $enabled->execute([$moduleKey]);
        if ((int)$enabled->fetchColumn() !== 0) throw new PluginLifecycleException('PLUGIN_TENANT_MODULE_ACTIVE', 'Disable every TenantModule first.');
        $this->pdo->beginTransaction();
        try {
            (new ModuleCatalogMutationRepository($this->pdo))->retire([$moduleKey]);
            $update = $this->pdo->prepare("UPDATE pa_module_installation SET status='maintenance',last_error_code=NULL,revision=revision+1,updated_at=UTC_TIMESTAMP(3) WHERE module_key=? AND status='active'");
            $update->execute([$moduleKey]);
            $this->pdo->commit();
        } catch (\Throwable $exception) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            throw $exception;
        }
        $catalog = $this->catalog();
        $catalog->invalidateTenantAuthorization([$moduleKey]);
        return ['operation' => 'disabled', 'module_key' => $moduleKey, 'status' => 'maintenance', 'catalog_revision' => $catalog->catalogRevision()];
    }

    /** @return array<string,mixed> */
    public function sync(?string $moduleKey): array
    {
        $result = $this->catalog()->sync($moduleKey);
        if ($result['operation'] !== 'unchanged') {
            $this->catalog()->invalidateTenantAuthorization($result['modules']);
        }
        return $result;
    }

    private function catalog(): PluginCatalogSyncService
    {
        return new PluginCatalogSyncService($this->pdo, $this->serverRoot, $this->moduleConfig);
    }
}
