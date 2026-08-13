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
use PeanutAdmin\Kernel\Module\ManifestDocument;
use PeanutAdmin\Settings\Definition\SettingDefinitionLoader;
use PeanutAdmin\Settings\Definition\SettingDefinitionRegistry;
use PeanutAdmin\Settings\Persistence\PdoSettingRepository;

/** Deployment-scoped Plugin lifecycle. It deliberately never mutates pa_tenant_module. */
final readonly class PluginLifecycleService
{
    /** @param array<string,mixed> $moduleConfig */
    public function __construct(
        private PDO $pdo,
        private PluginLockResolver $resolver,
        private PluginModuleRegistryFactory $registries,
        private array $moduleConfig
    ) {
    }

    /** @return array<string,mixed> */
    public function install(string $pluginKey): array
    {
        $plugin = $this->resolver->require($pluginKey);
        $manifests = $this->pluginManifests($plugin);
        $this->assertPreflightOwnership($plugin, $manifests, false);
        $current = $this->pluginInstallation($pluginKey, true);
        if (is_array($current) && $this->sameIdentity($plugin, $current) && $current['status'] === 'active') {
            return $plugin->publicIdentity() + ['operation' => 'unchanged'];
        }
        if (is_array($current) && !in_array($current['status'], ['failed', 'uninstalled'], true)) {
            throw new PluginLifecycleException('PLUGIN_ALREADY_INSTALLED', 'Use plugin:upgrade for an installed Plugin.');
        }
        return $this->activate($plugin, $manifests, false);
    }

    /** @return array<string,mixed> */
    public function upgrade(string $pluginKey, bool $dryRun): array
    {
        $plugin = $this->resolver->require($pluginKey);
        $manifests = $this->pluginManifests($plugin);
        $this->assertPreflightOwnership($plugin, $manifests, true);
        $current = $this->pluginInstallation($pluginKey, false);
        if (!is_array($current)) {
            throw new PluginLifecycleException('PLUGIN_NOT_INSTALLED', 'Plugin must be installed before upgrade.');
        }
        if (!in_array($current['status'], ['active', 'failed'], true)) {
            throw new PluginLifecycleException('PLUGIN_STATE_INVALID', 'Plugin cannot be upgraded from its current state.');
        }
        if (version_compare($plugin->version, (string)$current['installed_version'], '<')) {
            throw new PluginLifecycleException('PLUGIN_DOWNGRADE_REJECTED', 'Upgrade cannot install an older Plugin version.');
        }
        $plan = $this->plan($plugin, $manifests, $current);
        if ($dryRun || ($this->sameIdentity($plugin, $current) && $current['status'] === 'active')) {
            return $plan + ['dry_run' => $dryRun, 'operation' => $dryRun ? 'upgrade' : 'unchanged'];
        }
        return $this->activate($plugin, $manifests, true) + ['plan' => $plan];
    }

    /** @return array<string,mixed> */
    public function rollbackPlan(string $pluginKey): array
    {
        $current = $this->pluginInstallation($pluginKey, false);
        if (!is_array($current)) {
            throw new PluginLifecycleException('PLUGIN_NOT_INSTALLED', 'Plugin is not installed.');
        }
        $migrations = $this->pdo->prepare(<<<'SQL'
SELECT module_key,migration_key,module_version,checksum,status
FROM pa_module_migration
WHERE module_key IN (SELECT module_key FROM pa_plugin_module WHERE plugin_key=:plugin_key)
ORDER BY id DESC
SQL);
        $migrations->execute(['plugin_key' => $pluginKey]);
        return [
            'plugin_key' => $pluginKey,
            'installed_version' => (string)$current['installed_version'],
            'operation' => 'rollback-plan',
            'automatic' => false,
            'preserve_data' => true,
            'steps' => [
                'place the previously verified Plugin artifact in plugins.lock',
                'restore a verified database backup when an applied migration is irreversible',
                'run plugin:upgrade with the restored immutable identity',
            ],
            'applied_migrations' => $migrations->fetchAll(PDO::FETCH_ASSOC),
        ];
    }

    /** @return array<string,mixed> */
    public function uninstall(string $pluginKey): array
    {
        $current = $this->pluginInstallation($pluginKey, false);
        if (!is_array($current) || $current['status'] === 'uninstalled') {
            throw new PluginLifecycleException('PLUGIN_NOT_INSTALLED', 'Plugin is not installed.');
        }
        $enabled = $this->pdo->prepare(<<<'SQL'
SELECT COUNT(*) FROM pa_tenant_module
WHERE module_key IN (SELECT module_key FROM pa_plugin_module WHERE plugin_key=:plugin_key)
  AND status='enabled'
SQL);
        $enabled->execute(['plugin_key' => $pluginKey]);
        if ((int)$enabled->fetchColumn() !== 0) {
            throw new PluginLifecycleException('PLUGIN_TENANT_MODULE_ACTIVE', 'Disable every TenantModule before uninstall.');
        }
        $modules = $this->pluginModuleRows($pluginKey);
        $now = $this->now();
        $this->pdo->beginTransaction();
        try {
            $module = $this->pdo->prepare(<<<'SQL'
UPDATE pa_module_installation
SET status='maintenance',revision=revision+1,updated_at=:updated_at
WHERE module_key=:module_key
SQL);
            foreach ($modules as $row) {
                $module->execute(['module_key' => $row['module_key'], 'updated_at' => $now]);
            }
            $plugin = $this->pdo->prepare(<<<'SQL'
UPDATE pa_plugin_installation
SET status='uninstalled',revision=revision+1,uninstalled_at=:now,last_error_code=NULL,updated_at=:now
WHERE plugin_key=:plugin_key
SQL);
            $plugin->execute(['plugin_key' => $pluginKey, 'now' => $now]);
            $this->pdo->commit();
        } catch (\Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $exception;
        }
        return [
            'plugin_key' => $pluginKey,
            'operation' => 'uninstall',
            'status' => 'uninstalled',
            'preserve_data' => true,
            'preserved' => ['module_migrations', 'plugin_module_ownership', 'business_data'],
        ];
    }

    /** @param array<string,ManifestDocument> $manifests @return array<string,mixed> */
    private function activate(PluginDescriptor $plugin, array $manifests, bool $upgrade): array
    {
        $this->beginLifecycle($plugin, $manifests, $upgrade);
        try {
            $this->applyMigrations($plugin, $manifests);
            $this->registerCatalog($plugin, $manifests, $upgrade);
            return $plugin->publicIdentity() + ['operation' => $upgrade ? 'upgraded' : 'installed'];
        } catch (\Throwable $exception) {
            $errorCode = $exception instanceof PluginLifecycleException
                ? $exception->errorCode
                : 'PLUGIN_LIFECYCLE_FAILED';
            $this->markFailed($plugin, $manifests, $errorCode);
            throw $exception;
        }
    }

    /** @param array<string,ManifestDocument> $manifests */
    private function beginLifecycle(PluginDescriptor $plugin, array $manifests, bool $upgrade): void
    {
        $now = $this->now();
        $this->pdo->beginTransaction();
        try {
            $statement = $this->pdo->prepare(<<<'SQL'
INSERT INTO pa_plugin_installation (
 plugin_key,installed_version,source,artifact_sha256,lock_digest,
 composer_identity_json,npm_identity_json,frontend_identity_json,status,revision,
 installed_at,created_at,updated_at
) VALUES (
 :plugin_key,:version,:source,:artifact_sha256,:lock_digest,
 :composer,:npm,:frontend,'installing',1,:now,:now,:now
)
ON DUPLICATE KEY UPDATE
 installed_version=VALUES(installed_version),source=VALUES(source),
 artifact_sha256=VALUES(artifact_sha256),lock_digest=VALUES(lock_digest),
 composer_identity_json=VALUES(composer_identity_json),npm_identity_json=VALUES(npm_identity_json),
 frontend_identity_json=VALUES(frontend_identity_json),status=VALUES(status),
 revision=revision+1,last_error_code=NULL,updated_at=VALUES(updated_at)
SQL);
            $statement->execute($this->pluginParameters($plugin, $now));
            $module = $this->pdo->prepare(<<<'SQL'
INSERT INTO pa_module_installation (
 module_key,installed_version,manifest_schema_version,manifest_digest,status,revision,
 installed_at,created_at,updated_at
) VALUES (:module_key,:version,:schema,:digest,:status,1,:now,:now,:now)
ON DUPLICATE KEY UPDATE
 installed_version=VALUES(installed_version),manifest_schema_version=VALUES(manifest_schema_version),
 manifest_digest=VALUES(manifest_digest),status=VALUES(status),revision=revision+1,
 last_error_code=NULL,updated_at=VALUES(updated_at)
SQL);
            foreach ($manifests as $manifest) {
                $module->execute([
                    'module_key' => $manifest->data['key'],
                    'version' => $manifest->data['version'],
                    'schema' => $manifest->data['schema_version'],
                    'digest' => $manifest->digest,
                    'status' => $upgrade ? 'upgrading' : 'installing',
                    'now' => $now,
                ]);
            }
            $this->pdo->commit();
        } catch (\Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $exception;
        }
    }

    /** @param array<string,ManifestDocument> $manifests */
    private function assertPreflightOwnership(
        PluginDescriptor $plugin,
        array $manifests,
        bool $upgrade
    ): void {
        $owner = $this->pdo->prepare('SELECT plugin_key FROM pa_plugin_module WHERE module_key=:module_key');
        foreach ($manifests as $moduleKey => $_manifest) {
            $owner->execute(['module_key' => $moduleKey]);
            $existing = $owner->fetchColumn();
            if (is_string($existing) && $existing !== $plugin->key) {
                throw new PluginLifecycleException('PLUGIN_MODULE_CONFLICT', "Module has another Plugin owner: {$moduleKey}");
            }
            if ($upgrade && !is_string($existing)) {
                throw new PluginLifecycleException('PLUGIN_MODULE_OWNERSHIP_MISSING', "Plugin does not own Module: {$moduleKey}");
            }
        }
    }

    /** @param array<string,ManifestDocument> $manifests */
    private function applyMigrations(PluginDescriptor $plugin, array $manifests): void
    {
        $batch = (int)$this->pdo->query('SELECT COALESCE(MAX(batch_no),0)+1 FROM pa_module_migration')->fetchColumn();
        foreach ($manifests as $moduleKey => $manifest) {
            foreach ($this->migrationFiles($plugin->moduleRoots[$moduleKey], $manifest) as $migrationKey => $path) {
                $checksum = hash_file('sha256', $path);
                if (!is_string($checksum)) {
                    throw new PluginLifecycleException('MODULE_MIGRATION_INVALID', "Migration is unreadable: {$path}");
                }
                $existing = $this->pdo->prepare(<<<'SQL'
SELECT checksum,status FROM pa_module_migration
WHERE module_key=:module_key AND migration_key=:migration_key
SQL);
                $existing->execute(['module_key' => $moduleKey, 'migration_key' => $migrationKey]);
                $row = $existing->fetch(PDO::FETCH_ASSOC);
                if (is_array($row)) {
                    if (!hash_equals((string)$row['checksum'], $checksum)) {
                        throw new PluginLifecycleException(
                            'MODULE_MIGRATION_CHECKSUM_MISMATCH',
                            "Applied Module migration changed: {$migrationKey}"
                        );
                    }
                    if ($row['status'] === 'applied') {
                        continue;
                    }
                    throw new PluginLifecycleException('MODULE_MIGRATION_FAILED', "Migration is not append-only: {$migrationKey}");
                }
                $now = $this->now();
                $insert = $this->pdo->prepare(<<<'SQL'
INSERT INTO pa_module_migration (
 module_key,migration_key,module_version,checksum,batch_no,status,started_at
) VALUES (:module_key,:migration_key,:module_version,:checksum,:batch_no,'applying',:started_at)
SQL);
                $this->pdo->beginTransaction();
                try {
                    $insert->execute([
                        'module_key' => $moduleKey,
                        'migration_key' => $migrationKey,
                        'module_version' => $manifest->data['version'],
                        'checksum' => $checksum,
                        'batch_no' => $batch,
                        'started_at' => $now,
                    ]);
                    $this->pdo->commit();
                } catch (\Throwable $exception) {
                    if ($this->pdo->inTransaction()) {
                        $this->pdo->rollBack();
                    }
                    throw $exception;
                }
                try {
                    $sql = trim((string)file_get_contents($path));
                    if ($sql === '') {
                        throw new PluginLifecycleException('MODULE_MIGRATION_INVALID', "Migration is empty: {$migrationKey}");
                    }
                    // MySQL DDL commits implicitly. Keep the durable migration ledger outside the DDL boundary.
                    $this->pdo->exec($sql);
                    $this->pdo->beginTransaction();
                    $finish = $this->pdo->prepare(<<<'SQL'
UPDATE pa_module_migration SET status='applied',finished_at=:finished_at,error_code=NULL
WHERE module_key=:module_key AND migration_key=:migration_key
SQL);
                    $finish->execute([
                        'finished_at' => $this->now(),
                        'module_key' => $moduleKey,
                        'migration_key' => $migrationKey,
                    ]);
                    $this->pdo->commit();
                } catch (\Throwable $exception) {
                    if ($this->pdo->inTransaction()) {
                        $this->pdo->rollBack();
                    }
                    $failed = $this->pdo->prepare(<<<'SQL'
UPDATE pa_module_migration
SET status='failed',finished_at=:finished_at,error_code=:error_code
WHERE module_key=:module_key AND migration_key=:migration_key AND status='applying'
SQL);
                    $failed->execute([
                        'finished_at' => $this->now(),
                        'error_code' => $exception instanceof PluginLifecycleException
                            ? $exception->errorCode
                            : 'MODULE_MIGRATION_FAILED',
                        'module_key' => $moduleKey,
                        'migration_key' => $migrationKey,
                    ]);
                    throw $exception;
                }
            }
        }
    }

    /** @param array<string,ManifestDocument> $manifests */
    private function registerCatalog(PluginDescriptor $plugin, array $manifests, bool $upgrade): void
    {
        $now = $this->now();
        $this->pdo->beginTransaction();
        try {
            $compiled = $this->registries
                ->fromPluginLock($this->resolver, $this->moduleConfig)
                ->compiled();
            (new ModuleAuthorizationCatalogSynchronizer(
                new PdoAuthorizationCatalogRepository($this->pdo)
            ))->synchronize($compiled);
            (new MenuCatalogSynchronizer(new PdoMenuCatalogRepository($this->pdo)))
                ->synchronize($compiled);
            $settings = new SettingDefinitionRegistry();
            $settingLoader = new SettingDefinitionLoader();
            foreach ($manifests as $moduleKey => $manifest) {
                $backend = is_array($manifest->data['backend'] ?? null)
                    ? $manifest->data['backend']
                    : [];
                $resource = $backend['setting_definitions'] ?? null;
                $definitions = is_string($resource)
                    ? $settingLoader->load(
                        $moduleKey,
                        $plugin->moduleRoots[$moduleKey] . '/' . ltrim($resource, '/')
                    )
                    : [];
                $settings->registerModule($moduleKey, $definitions);
            }
            (new PdoSettingRepository($this->pdo))->synchronize(
                $settings,
                new DateTimeImmutable('now', new DateTimeZone('UTC'))
            );
            $owner = $this->pdo->prepare('SELECT plugin_key FROM pa_plugin_module WHERE module_key=:module_key FOR UPDATE');
            $catalog = $this->pdo->prepare(<<<'SQL'
INSERT INTO pa_plugin_module (plugin_key,module_key,module_version,manifest_digest,created_at,updated_at)
VALUES (:plugin_key,:module_key,:module_version,:manifest_digest,:now,:now)
ON DUPLICATE KEY UPDATE
 module_version=VALUES(module_version),manifest_digest=VALUES(manifest_digest),updated_at=VALUES(updated_at)
SQL);
            $module = $this->pdo->prepare(<<<'SQL'
UPDATE pa_module_installation
SET installed_version=:version,manifest_schema_version=:schema,manifest_digest=:digest,
 status='active',revision=revision+1,activated_at=COALESCE(activated_at,:now),
 upgraded_at=:upgraded_at,last_error_code=NULL,updated_at=:now
WHERE module_key=:module_key
SQL);
            foreach ($manifests as $moduleKey => $manifest) {
                $owner->execute(['module_key' => $moduleKey]);
                $existingOwner = $owner->fetchColumn();
                if (is_string($existingOwner) && $existingOwner !== $plugin->key) {
                    throw new PluginLifecycleException('PLUGIN_MODULE_CONFLICT', "Module has another Plugin owner: {$moduleKey}");
                }
                $catalog->execute([
                    'plugin_key' => $plugin->key,
                    'module_key' => $moduleKey,
                    'module_version' => $manifest->data['version'],
                    'manifest_digest' => $manifest->digest,
                    'now' => $now,
                ]);
                $module->execute([
                    'module_key' => $moduleKey,
                    'version' => $manifest->data['version'],
                    'schema' => $manifest->data['schema_version'],
                    'digest' => $manifest->digest,
                    'upgraded_at' => $upgrade ? $now : null,
                    'now' => $now,
                ]);
            }
            $pluginUpdate = $this->pdo->prepare(<<<'SQL'
UPDATE pa_plugin_installation SET
 installed_version=:version,source=:source,artifact_sha256=:artifact_sha256,lock_digest=:lock_digest,
 composer_identity_json=:composer,npm_identity_json=:npm,frontend_identity_json=:frontend,
 status='active',revision=revision+1,activated_at=COALESCE(activated_at,:now),
 upgraded_at=:upgraded_at,uninstalled_at=NULL,last_error_code=NULL,updated_at=:now
WHERE plugin_key=:plugin_key
SQL);
            $pluginUpdate->execute($this->pluginParameters($plugin, $now) + [
                'upgraded_at' => $upgrade ? $now : null,
            ]);
            $this->pdo->commit();
        } catch (\Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $exception;
        }
    }

    /** @param array<string,ManifestDocument> $manifests */
    private function markFailed(PluginDescriptor $plugin, array $manifests, string $errorCode): void
    {
        try {
            $now = $this->now();
            $this->pdo->beginTransaction();
            $statement = $this->pdo->prepare(<<<'SQL'
UPDATE pa_plugin_installation
SET status='failed',revision=revision+1,last_error_code=:error_code,updated_at=:now
WHERE plugin_key=:plugin_key
SQL);
            $statement->execute(['plugin_key' => $plugin->key, 'error_code' => $errorCode, 'now' => $now]);
            $module = $this->pdo->prepare(<<<'SQL'
UPDATE pa_module_installation SET status='failed',revision=revision+1,last_error_code=:error_code,updated_at=:now
WHERE module_key=:module_key
SQL);
            foreach ($manifests as $moduleKey => $_manifest) {
                $module->execute(['module_key' => $moduleKey, 'error_code' => $errorCode, 'now' => $now]);
            }
            $this->pdo->commit();
        } catch (\Throwable) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
        }
    }

    /** @return array<string,ManifestDocument> */
    private function pluginManifests(PluginDescriptor $plugin): array
    {
        $registry = $this->registries->fromPluginLock($this->resolver, $this->moduleConfig);
        $manifests = [];
        foreach ($registry->compiled()->modules as $manifest) {
            $key = (string)($manifest->data['key'] ?? '');
            if (isset($plugin->moduleRoots[$key])) {
                $manifests[$key] = $manifest;
            }
        }
        $compiledKeys = array_keys($manifests);
        $lockedKeys = array_keys($plugin->moduleRoots);
        sort($compiledKeys, SORT_STRING);
        sort($lockedKeys, SORT_STRING);
        if ($compiledKeys !== $lockedKeys) {
            throw new PluginLifecycleException('PLUGIN_MODULE_MISMATCH', 'Compiled Plugin modules differ from plugins.lock.');
        }
        ksort($manifests, SORT_STRING);
        return $manifests;
    }

    /** @param array<string,ManifestDocument> $manifests @param array<string,mixed> $current */
    private function plan(PluginDescriptor $plugin, array $manifests, array $current): array
    {
        $pending = [];
        foreach ($manifests as $moduleKey => $manifest) {
            foreach ($this->migrationFiles($plugin->moduleRoots[$moduleKey], $manifest) as $key => $path) {
                $statement = $this->pdo->prepare(<<<'SQL'
SELECT checksum,status FROM pa_module_migration WHERE module_key=:module_key AND migration_key=:migration_key
SQL);
                $statement->execute(['module_key' => $moduleKey, 'migration_key' => $key]);
                $row = $statement->fetch(PDO::FETCH_ASSOC);
                $checksum = (string)hash_file('sha256', $path);
                if (is_array($row) && !hash_equals((string)$row['checksum'], $checksum)) {
                    throw new PluginLifecycleException('MODULE_MIGRATION_CHECKSUM_MISMATCH', "Migration changed: {$key}");
                }
                if (!is_array($row) || $row['status'] !== 'applied') {
                    $pending[] = ['module_key' => $moduleKey, 'migration_key' => $key, 'sha256' => $checksum];
                }
            }
        }
        return [
            'plugin_key' => $plugin->key,
            'from_version' => (string)$current['installed_version'],
            'to_version' => $plugin->version,
            'identity_changed' => !$this->sameIdentity($plugin, $current),
            'pending_migrations' => $pending,
            'rollback' => ['automatic' => false, 'requires_verified_backup' => $pending !== []],
        ];
    }

    /** @return array<string,string> */
    private function migrationFiles(string $root, ManifestDocument $manifest): array
    {
        $directories = [];
        $backend = $manifest->data['backend'] ?? null;
        if (is_array($backend) && is_string($backend['migrations'] ?? null)) {
            $directories[] = $root . '/' . ltrim($backend['migrations'], '/');
        }
        $directories[] = $root . '/Database/Migrations';
        $directories[] = $root . '/database/migrations';
        $files = [];
        $resolvedDirectories = [];
        foreach ($directories as $directory) {
            $resolved = realpath($directory);
            if ($resolved === false || isset($resolvedDirectories[$resolved])) {
                continue;
            }
            $resolvedDirectories[$resolved] = true;
        }
        foreach (array_keys($resolvedDirectories) as $directory) {
            if (!is_dir($directory)) {
                continue;
            }
            foreach (glob($directory . '/*.sql') ?: [] as $path) {
                $key = (string)$manifest->data['key'] . ':' . basename($path, '.sql');
                if (isset($files[$key])) {
                    throw new PluginLifecycleException('MODULE_MIGRATION_INVALID', "Duplicate migration key: {$key}");
                }
                $files[$key] = $path;
            }
        }
        ksort($files, SORT_STRING);
        return $files;
    }

    /** @return array<string,mixed>|false */
    private function pluginInstallation(string $pluginKey, bool $lock): array|false
    {
        $sql = 'SELECT * FROM pa_plugin_installation WHERE plugin_key=:plugin_key' . ($lock ? ' FOR UPDATE' : '');
        $statement = $this->pdo->prepare($sql);
        $statement->execute(['plugin_key' => $pluginKey]);
        return $statement->fetch(PDO::FETCH_ASSOC);
    }

    /** @return list<array<string,mixed>> */
    private function pluginModuleRows(string $pluginKey): array
    {
        $statement = $this->pdo->prepare('SELECT * FROM pa_plugin_module WHERE plugin_key=:plugin_key ORDER BY module_key');
        $statement->execute(['plugin_key' => $pluginKey]);
        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    /** @param array<string,mixed> $current */
    private function sameIdentity(PluginDescriptor $plugin, array $current): bool
    {
        return (string)$current['installed_version'] === $plugin->version
            && hash_equals((string)$current['artifact_sha256'], $plugin->source['sha256'])
            && hash_equals((string)$current['lock_digest'], $plugin->lockDigest)
            && $this->jsonColumn($current['composer_identity_json']) === $this->json($plugin->composer)
            && $this->jsonColumn($current['npm_identity_json']) === $this->json($plugin->npm)
            && $this->jsonColumn($current['frontend_identity_json']) === $this->json($plugin->frontend);
    }

    /** @return array<string,mixed> */
    private function pluginParameters(PluginDescriptor $plugin, string $now): array
    {
        return [
            'plugin_key' => $plugin->key,
            'version' => $plugin->version,
            'source' => $plugin->source['type'] . ':' . $plugin->source['reference'],
            'artifact_sha256' => $plugin->source['sha256'],
            'lock_digest' => $plugin->lockDigest,
            'composer' => $this->json($plugin->composer),
            'npm' => $this->json($plugin->npm),
            'frontend' => $this->json($plugin->frontend),
            'now' => $now,
        ];
    }

    private function json(mixed $value): string
    {
        return (string)json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
    }

    private function jsonColumn(mixed $value): string
    {
        if (!is_string($value)) {
            return '';
        }
        try {
            return $this->json(json_decode($value, true, 64, JSON_THROW_ON_ERROR));
        } catch (\JsonException) {
            return '';
        }
    }

    private function now(): string
    {
        return gmdate('Y-m-d H:i:s.v');
    }
}
