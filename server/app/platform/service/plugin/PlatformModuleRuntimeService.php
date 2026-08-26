<?php
declare(strict_types=1);

namespace app\platform\service\plugin;

use app\common\service\module\ModuleScaffoldGenerator;
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
            $packageDetails = [];
            $packageProtected = false;
            foreach ($descriptor->moduleRoots as $key => $root) {
                $manifest = (new ManifestLoader())->load($root);
                $packageProtected = $packageProtected || ModuleLifecyclePolicy::isProtected($manifest);
                $dependencies = [];
                foreach ((array)($manifest->data['dependencies'] ?? []) as $dependency) {
                    if (!is_array($dependency) || !is_string($dependency['module_key'] ?? null)) continue;
                    $dependencies[] = ['module_key' => $dependency['module_key'], 'version' => (string)($dependency['version'] ?? '')];
                    $dependents[$dependency['module_key']][] = $key;
                }
                $packageDetails[$key] = [
                    'module_key' => $key,
                    'name' => (string)($manifest->data['name'] ?? $key),
                    'version' => (string)($manifest->data['version'] ?? ''),
                    'manifest_digest' => $manifest->digest,
                    'package_key' => $descriptor->key,
                    'package_version' => $descriptor->version,
                    'dependencies' => $dependencies,
                ];
            }
            $packageModules = array_keys($packageDetails);
            sort($packageModules, SORT_STRING);
            foreach ($packageDetails as $key => $detail) {
                $detail['package_modules'] = $packageModules;
                $detail['lifecycle_protected'] = $packageProtected;
                $details[$key] = $detail;
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
                'package_modules' => [$key],
                'lifecycle_protected' => false,
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
    public function create(string $moduleKey, ?string $vendor = null): array
    {
        return (new ModuleScaffoldGenerator(dirname($this->serverRoot)))->create($moduleKey, $vendor);
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
        $scope = $this->disableScope($moduleKey);
        $packageKey = $scope['package_key'];
        $moduleKeys = array_keys($scope['manifests']);
        $lockName = 'pa:module-runtime:' . substr(hash('sha256', $packageKey), 0, 40);
        if (!$this->advisoryLock($lockName)) {
            throw new PluginLifecycleException('MODULE_LIFECYCLE_BUSY', 'Module lifecycle is busy.');
        }
        try {
            ModuleLifecyclePolicy::assertMutable($scope['manifests']);
            $statuses = $this->moduleStatuses($moduleKeys);
            if (count($statuses) !== count($moduleKeys)
                || array_diff(array_values($statuses), ['active', 'maintenance']) !== []) {
                throw new PluginLifecycleException('MODULE_STATE_INVALID', 'Every Bundle Module must be active or already disabled.');
            }
            if (count(array_filter($statuses, static fn(string $status): bool => $status === 'maintenance')) === count($moduleKeys)) {
                return [
                    'operation' => 'unchanged',
                    'package_key' => $packageKey,
                    'affected_modules' => $moduleKeys,
                    'status' => 'maintenance',
                    'catalog_revision' => $this->catalog()->catalogRevision(),
                ];
            }
            ModuleLifecyclePolicy::assertNoActiveBusinessDependents(
                $this->pdo,
                new PluginLockResolver(
                    $this->serverRoot,
                    (string)($this->moduleConfig['plugin_lock'] ?? '../plugins.lock'),
                ),
                $moduleKeys,
            );
            $enabled = $this->pdo->prepare('SELECT COUNT(*) FROM pa_tenant_module WHERE module_key IN (' . $this->placeholders($moduleKeys) . ") AND status='enabled'");
            $enabled->execute($moduleKeys);
            if ((int)$enabled->fetchColumn() !== 0) {
                throw new PluginLifecycleException('PLUGIN_TENANT_MODULE_ACTIVE', 'Disable every TenantModule in the Bundle first.');
            }
            $this->pdo->beginTransaction();
            try {
                (new ModuleCatalogApplier($this->pdo))->retire($moduleKeys);
                $update = $this->pdo->prepare("UPDATE pa_module_installation SET status='maintenance',last_error_code=NULL,revision=revision+1,updated_at=UTC_TIMESTAMP(3) WHERE module_key IN (" . $this->placeholders($moduleKeys) . ") AND status='active'");
                $update->execute($moduleKeys);
                $this->pdo->commit();
            } catch (\Throwable $exception) {
                if ($this->pdo->inTransaction()) $this->pdo->rollBack();
                throw $exception;
            }
        } finally {
            $this->releaseAdvisoryLock($lockName);
        }
        $catalog = $this->catalog();
        $catalog->invalidateTenantAuthorization($moduleKeys);
        return [
            'operation' => 'disabled',
            'package_key' => $packageKey,
            'affected_modules' => $moduleKeys,
            'status' => 'maintenance',
            'catalog_revision' => $catalog->catalogRevision(),
        ];
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

    /** @return array{package_key:string,manifests:array<string,\PeanutAdmin\Kernel\Module\ManifestDocument>} */
    private function disableScope(string $moduleKey): array
    {
        $owner = $this->pdo->prepare('SELECT plugin_key FROM pa_plugin_module WHERE module_key=?');
        $owner->execute([$moduleKey]);
        $packageKey = $owner->fetchColumn();
        if (!is_string($packageKey) || $packageKey === '') {
            throw new PluginLifecycleException('PLUGIN_NOT_INSTALLED', 'Module package is not installed.');
        }
        $descriptor = (new PluginLockResolver(
            $this->serverRoot,
            (string)($this->moduleConfig['plugin_lock'] ?? '../plugins.lock'),
        ))->require($packageKey);
        $manifests = [];
        foreach ($descriptor->moduleRoots as $key => $root) {
            $manifests[$key] = (new ManifestLoader())->load($root);
        }
        ksort($manifests, SORT_STRING);
        return ['package_key' => $packageKey, 'manifests' => $manifests];
    }

    /** @param list<string> $moduleKeys @return array<string,string> */
    private function moduleStatuses(array $moduleKeys): array
    {
        $statement = $this->pdo->prepare('SELECT module_key,status FROM pa_module_installation WHERE module_key IN (' . $this->placeholders($moduleKeys) . ') ORDER BY module_key');
        $statement->execute($moduleKeys);
        return array_map('strval', $statement->fetchAll(PDO::FETCH_KEY_PAIR));
    }

    /** @param list<mixed> $values */
    private function placeholders(array $values): string
    {
        return implode(',', array_fill(0, count($values), '?'));
    }

    private function advisoryLock(string $name): bool
    {
        $statement = $this->pdo->prepare('SELECT GET_LOCK(?,0)');
        $statement->execute([$name]);
        return (int)$statement->fetchColumn() === 1;
    }

    private function releaseAdvisoryLock(string $name): void
    {
        try {
            $statement = $this->pdo->prepare('SELECT RELEASE_LOCK(?)');
            $statement->execute([$name]);
        } catch (\Throwable) {
        }
    }
}
