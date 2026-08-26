<?php
declare(strict_types=1);

namespace app\platform\service\plugin;

use DateTimeImmutable;
use DateTimeZone;
use PDO;
use PeanutAdmin\Kernel\Authorization\ModuleAuthorizationCatalogSynchronizer;
use PeanutAdmin\Kernel\Authorization\Persistence\PdoAuthorizationCatalogRepository;
use PeanutAdmin\Kernel\Menu\MenuCatalogSynchronizer;
use PeanutAdmin\Kernel\Menu\PdoMenuCatalogRepository;
use PeanutAdmin\Settings\Definition\SettingDefinitionLoader;
use PeanutAdmin\Settings\Definition\SettingDefinitionRegistry;
use PeanutAdmin\Settings\Persistence\PdoSettingRepository;

/** Applies module.json catalog contributions through the same compiled Plugin registry used at runtime. */
final readonly class PluginCatalogSyncService
{
    /** @param array<string,mixed> $moduleConfig */
    public function __construct(
        private PDO $pdo,
        private string $serverRoot,
        private array $moduleConfig,
    ) {
    }

    /** @return array<string,mixed> */
    public function sync(?string $moduleKey = null): array
    {
        $resolver = new PluginLockResolver($this->serverRoot, (string)($this->moduleConfig['plugin_lock'] ?? '../plugins.lock'));
        $compiled = (new PluginModuleRegistryFactory($this->pdo, $this->serverRoot))
            ->fromPluginLock($resolver, $this->moduleConfig)
            ->compiled();
        $manifests = [];
        foreach ($compiled->modules as $manifest) {
            $key = (string)($manifest->data['key'] ?? '');
            if ($moduleKey === null || $key === $moduleKey) $manifests[$key] = $manifest;
        }
        if ($moduleKey !== null && !isset($manifests[$moduleKey])) {
            throw new PluginLifecycleException('MODULE_NOT_REGISTERED', 'Module is not active in the deployed registry.');
        }
        $before = $this->catalogDigest();
        $this->pdo->beginTransaction();
        try {
            (new ModuleAuthorizationCatalogSynchronizer(new PdoAuthorizationCatalogRepository($this->pdo)))->synchronize($compiled);
            (new MenuCatalogSynchronizer(new PdoMenuCatalogRepository($this->pdo)))->synchronize($compiled);
            $settings = new SettingDefinitionRegistry();
            $loader = new SettingDefinitionLoader();
            foreach ($manifests as $key => $manifest) {
                $backend = is_array($manifest->data['backend'] ?? null) ? $manifest->data['backend'] : [];
                $resource = $backend['setting_definitions'] ?? null;
                $definitions = is_string($resource)
                    ? $loader->load($key, $manifest->root . '/' . ltrim($resource, '/'))
                    : [];
                $settings->registerModule($key, $definitions);
            }
            (new PdoSettingRepository($this->pdo))->synchronize($settings, new DateTimeImmutable('now', new DateTimeZone('UTC')));
            $this->pdo->commit();
        } catch (\Throwable $exception) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            throw $exception;
        }
        $after = $this->catalogDigest();
        $keys = array_keys($manifests);
        sort($keys, SORT_STRING);
        return [
            'operation' => hash_equals($before, $after) ? 'unchanged' : 'synced',
            'modules' => $keys,
            'catalog_revision' => $after,
            'changes' => $this->catalogCounts($keys),
        ];
    }

    public function catalogRevision(): string
    {
        return $this->catalogDigest();
    }

    /** @param list<string> $moduleKeys */
    public function invalidateTenantAuthorization(array $moduleKeys): void
    {
        if ($moduleKeys === []) return;
        $placeholders = implode(',', array_fill(0, count($moduleKeys), '?'));
        $this->pdo->beginTransaction();
        try {
            $tenants = $this->pdo->prepare("SELECT DISTINCT tenant_id FROM pa_tenant_module WHERE module_key IN ({$placeholders}) ORDER BY tenant_id FOR UPDATE");
            $tenants->execute($moduleKeys);
            $tenantIds = array_map('intval', $tenants->fetchAll(PDO::FETCH_COLUMN));
            $modules = $this->pdo->prepare("UPDATE pa_tenant_module SET authorization_revision=authorization_revision+1,updated_at=UTC_TIMESTAMP(3) WHERE module_key IN ({$placeholders})");
            $modules->execute($moduleKeys);
            if ($tenantIds !== []) {
                $tenantPlaceholders = implode(',', array_fill(0, count($tenantIds), '?'));
                $statement = $this->pdo->prepare("UPDATE pa_tenant SET authorization_revision=authorization_revision+1,revision=revision+1,updated_at=UTC_TIMESTAMP(3) WHERE id IN ({$tenantPlaceholders})");
                $statement->execute($tenantIds);
            }
            $this->pdo->commit();
        } catch (\Throwable $exception) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            throw $exception;
        }
    }

    private function catalogDigest(): string
    {
        $rows = [];
        foreach ([
            'pa_permission' => 'SELECT id,`key`,module_key,status,COALESCE(DATE_FORMAT(retired_at,"%Y-%m-%d %H:%i:%s.%f"),"") retired_at FROM pa_permission ORDER BY id',
            'pa_menu_definition' => 'SELECT id,`key`,module_key,status,manifest_digest FROM pa_menu_definition ORDER BY id',
            'pa_setting_definition' => 'SELECT id,module_key,setting_key,status,revision,definition_digest FROM pa_setting_definition ORDER BY id',
        ] as $table => $sql) {
            $rows[$table] = $this->pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
        }
        return hash('sha256', json_encode($rows, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
    }

    /** @param list<string> $moduleKeys @return array{menus:int,permissions:int,settings:int} */
    private function catalogCounts(array $moduleKeys): array
    {
        if ($moduleKeys === []) return ['menus' => 0, 'permissions' => 0, 'settings' => 0];
        $placeholders = implode(',', array_fill(0, count($moduleKeys), '?'));
        $counts = [];
        foreach (['menus' => 'pa_menu_definition', 'permissions' => 'pa_permission', 'settings' => 'pa_setting_definition'] as $name => $table) {
            $statement = $this->pdo->prepare("SELECT COUNT(*) FROM `{$table}` WHERE module_key IN ({$placeholders}) AND status='active'");
            $statement->execute($moduleKeys);
            $counts[$name] = (int)$statement->fetchColumn();
        }
        return $counts;
    }
}
