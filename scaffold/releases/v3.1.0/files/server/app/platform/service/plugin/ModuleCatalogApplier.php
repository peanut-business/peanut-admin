<?php
declare(strict_types=1);

namespace app\platform\service\plugin;

use app\common\persistence\CoreTenantRepositoryFactory;
use DateTimeImmutable;
use DateTimeZone;
use PDO;
use PeanutAdmin\Kernel\Authorization\ModuleAuthorizationCatalogSynchronizer;
use PeanutAdmin\Kernel\Authorization\Persistence\PdoAuthorizationCatalogRepository;
use PeanutAdmin\Kernel\Menu\MenuCatalogSynchronizer;
use PeanutAdmin\Kernel\Menu\PdoMenuCatalogRepository;
use PeanutAdmin\Kernel\Module\CompiledModuleRegistry;
use PeanutAdmin\Kernel\Module\ManifestDocument;
use PeanutAdmin\Settings\Definition\SettingDefinitionLoader;
use PeanutAdmin\Settings\Definition\SettingDefinitionRegistry;

/** The single application entry point for applying, retiring, and purging Module catalog contributions. */
final readonly class ModuleCatalogApplier
{
    public function __construct(private PDO $pdo)
    {
    }

    /**
     * @param null|list<string> $moduleKeys Null applies the complete compiled registry; a list applies only that scope.
     * @return array{operation:string,modules:list<string>,catalog_revision:string,changes:array{menus:int,permissions:int,settings:int}}
     */
    public function apply(CompiledModuleRegistry $registry, ?array $moduleKeys = null): array
    {
        $manifests = [];
        foreach ($registry->modules as $manifest) {
            $key = (string)($manifest->data['key'] ?? '');
            $manifests[$key] = $manifest;
        }
        $fullRegistry = $moduleKeys === null;
        $selectedKeys = $fullRegistry ? array_keys($manifests) : array_values(array_unique($moduleKeys));
        sort($selectedKeys, SORT_STRING);
        foreach ($selectedKeys as $key) {
            if (!isset($manifests[$key])) {
                throw new PluginLifecycleException('MODULE_NOT_REGISTERED', 'Module is not present in the compiled registry.');
            }
        }

        $selected = array_intersect_key($manifests, array_fill_keys($selectedKeys, true));
        $compiledScope = $this->scope($registry, $selected);
        $before = $this->catalogRevision();
        $ownsTransaction = !$this->pdo->inTransaction();
        if ($ownsTransaction) $this->pdo->beginTransaction();
        try {
            (new ModuleAuthorizationCatalogSynchronizer(new PdoAuthorizationCatalogRepository($this->pdo)))
                ->synchronize($compiledScope);

            $menuRepository = new PdoMenuCatalogRepository($this->pdo);
            $menus = $fullRegistry
                ? $menuRepository
                : new ScopedMenuCatalogRepository($this->pdo, $menuRepository, $selectedKeys);
            (new MenuCatalogSynchronizer($menus))->synchronize($fullRegistry ? $registry : $compiledScope);

            $settings = new SettingDefinitionRegistry();
            $loader = new SettingDefinitionLoader();
            foreach ($selected as $key => $manifest) {
                $backend = is_array($manifest->data['backend'] ?? null) ? $manifest->data['backend'] : [];
                $resource = $backend['setting_definitions'] ?? null;
                $definitions = is_string($resource)
                    ? $loader->load($key, $manifest->root . '/' . ltrim($resource, '/'))
                    : [];
                $settings->registerModule($key, $definitions);
            }
            (new CoreTenantRepositoryFactory($this->pdo))->settings()->synchronize(
                $settings,
                new DateTimeImmutable('now', new DateTimeZone('UTC')),
            );

            $mutations = new ModuleCatalogMutationRepository($this->pdo);
            $mutations->retireMissing($selected);
            if ($fullRegistry) {
                $absent = array_values(array_diff($mutations->activeModuleKeys(), $selectedKeys));
                if ($absent !== []) $mutations->retire($absent);
            }
            if ($ownsTransaction) $this->pdo->commit();
        } catch (\Throwable $exception) {
            if ($ownsTransaction && $this->pdo->inTransaction()) $this->pdo->rollBack();
            throw $exception;
        }

        $after = $this->catalogRevision();
        return [
            'operation' => hash_equals($before, $after) ? 'unchanged' : 'synced',
            'modules' => $selectedKeys,
            'catalog_revision' => $after,
            'changes' => $this->activeCounts($selectedKeys),
        ];
    }

    /** @param list<string> $moduleKeys */
    public function retire(array $moduleKeys): void
    {
        (new ModuleCatalogMutationRepository($this->pdo))->retire($moduleKeys);
    }

    /** @param list<string> $moduleKeys */
    public function purge(array $moduleKeys): void
    {
        (new ModuleCatalogMutationRepository($this->pdo))->purge($moduleKeys);
    }

    /** @param list<string> $moduleKeys @return array{removed:list<array<string,mixed>>,preserved:list<array<string,mixed>>,blockers:list<array<string,mixed>>} */
    public function plan(array $moduleKeys, bool $purge): array
    {
        return (new ModuleCatalogMutationRepository($this->pdo))->plan($moduleKeys, $purge);
    }

    public function catalogRevision(): string
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

    /** @param array<string,ManifestDocument> $manifests */
    private function scope(CompiledModuleRegistry $registry, array $manifests): CompiledModuleRegistry
    {
        $keys = array_fill_keys(array_keys($manifests), true);
        $menus = array_filter(
            $registry->menus,
            static fn(array $menu): bool => isset($keys[(string)($menu['module_key'] ?? '')]),
        );
        return new CompiledModuleRegistry(
            array_values($manifests),
            array_filter($registry->targetTypeOwners, static fn(string $owner): bool => isset($keys[$owner])),
            array_filter($registry->ownedTableOwners, static fn(string $owner): bool => isset($keys[$owner])),
            $menus,
            hash('sha256', implode('|', array_map(static fn(ManifestDocument $manifest): string => $manifest->digest, $manifests))),
        );
    }

    /** @param list<string> $moduleKeys @return array{menus:int,permissions:int,settings:int} */
    private function activeCounts(array $moduleKeys): array
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
