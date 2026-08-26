<?php
declare(strict_types=1);

namespace app\platform\service\plugin;

use PDO;
use PeanutAdmin\Kernel\Module\ManifestLoader;
use PeanutAdmin\Kernel\Module\ModuleHostLayout;
use PeanutAdmin\Kernel\Module\ModuleKey;

/** Deployment-scoped retire/purge state machine. It never mutates pa_tenant_module. */
final class PluginRuntimeGovernanceService
{
    /** @param array<string,mixed> $moduleConfig @param null|callable(string):void $faultInjector */
    public function __construct(
        private readonly PDO $pdo,
        private readonly string $serverRoot,
        private readonly array $moduleConfig,
        private readonly mixed $faultInjector = null,
    ) {
    }

    /** @return array<string,mixed> */
    public function preview(string $moduleOrPackageKey, bool $purge): array
    {
        $scope = $this->scope($moduleOrPackageKey);
        if ($scope === null) {
            return [
                'operation' => 'preview',
                'plan_digest' => $this->codec()->digest($plan = $this->cleanPlan($moduleOrPackageKey, $purge)),
                'confirm_plan' => $plan,
                'affected_modules' => $plan['affected_modules'],
                'preserved' => $plan['preserved'],
                'removed' => $plan['removed'],
                'blockers' => $plan['blockers'],
            ];
        }
        $plan = $this->buildPlan($scope, $purge);
        return [
            'operation' => 'preview',
            'plan_digest' => $this->codec()->digest($plan),
            'confirm_plan' => $plan,
            'affected_modules' => $plan['affected_modules'],
            'preserved' => $plan['preserved'],
            'removed' => $plan['removed'],
            'blockers' => $plan['blockers'],
        ];
    }

    /** @param array<string,mixed> $confirmPlan @return array<string,mixed> */
    public function uninstall(
        string $moduleOrPackageKey,
        bool $purge,
        array $confirmPlan,
        string $confirmPlanDigest,
    ): array {
        $codec = $this->codec();
        if (!hash_equals($codec->digest($confirmPlan), strtolower(trim($confirmPlanDigest)))) {
            throw new PluginLifecycleException('MODULE_UNINSTALL_PLAN_CHANGED', 'Confirmed Module uninstall plan digest differs.');
        }
        $operation = $purge ? 'purge' : 'retire';
        if (($confirmPlan['schema_version'] ?? null) !== 1 || ($confirmPlan['operation'] ?? null) !== $operation
            || !is_string($confirmPlan['package_key'] ?? null)
            || !is_array($confirmPlan['affected_modules'] ?? null)
            || !is_array($confirmPlan['removed'] ?? null)
            || !is_array($confirmPlan['preserved'] ?? null)
            || !is_array($confirmPlan['blockers'] ?? null)) {
            throw new PluginLifecycleException('MODULE_UNINSTALL_PLAN_CHANGED', 'Confirmed Module uninstall plan is invalid.');
        }
        $packageKey = (string)$confirmPlan['package_key'];
        if (!$this->inputBelongsToPackage($moduleOrPackageKey, $packageKey)) {
            throw new PluginLifecycleException('MODULE_UNINSTALL_PLAN_CHANGED', 'Confirmed package key differs.');
        }
        if ($confirmPlan['blockers'] !== []) {
            throw new PluginLifecycleException('MODULE_UNINSTALL_BLOCKED', 'Module uninstall plan contains blockers.');
        }

        $lockName = 'pa:module-runtime:' . substr(hash('sha256', $packageKey), 0, 40);
        if (!$this->advisoryLock($lockName)) {
            throw new PluginLifecycleException('MODULE_LIFECYCLE_BUSY', 'Module lifecycle is busy.');
        }
        try {
            $state = $this->installationState($packageKey);
            if ($this->isCleanState($state, $confirmPlan, $purge)) {
                return ['operation' => 'unchanged', 'package_key' => $packageKey, 'removed' => []];
            }
            $marker = $purge ? 'MODULE_PURGE_IN_PROGRESS' : 'MODULE_RETIRE_IN_PROGRESS';
            $resuming = is_array($state) && $state['status'] === 'maintenance' && $state['last_error_code'] === $marker;
            if (!$resuming) {
                $scope = $this->scope($moduleOrPackageKey);
                if ($scope === null) {
                    throw new PluginLifecycleException('PLUGIN_NOT_INSTALLED', 'Module package is not installed.');
                }
                $current = $this->buildPlan($scope, $purge);
                if (!hash_equals($codec->encode($confirmPlan), $codec->encode($current))) {
                    throw new PluginLifecycleException('MODULE_UNINSTALL_PLAN_CHANGED', 'Module uninstall plan changed before execution.');
                }
            } else {
                $this->assertResumeScope($confirmPlan, $packageKey);
            }

            $moduleKeys = $this->confirmedModuleKeys($confirmPlan);
            $ownedTablesByModule = $this->confirmedOwnedTablesByModule($confirmPlan);
            $ownedTables = $this->confirmedOwnedTables($ownedTablesByModule);
            $this->markMaintenance($packageKey, $moduleKeys, $marker);
            $this->inject('after-marker');

            $catalog = new ModuleCatalogApplier($this->pdo);
            $currentCatalog = $catalog->plan($moduleKeys, $purge);
            if ($currentCatalog['blockers'] !== []) {
                throw new PluginLifecycleException('MODULE_UNINSTALL_BLOCKED', 'New catalog references block Module uninstall.');
            }
            $this->assertCurrentRemovalSubset($currentCatalog['removed'], $confirmPlan['removed']);
            $purge ? $catalog->purge($moduleKeys) : $catalog->retire($moduleKeys);
            $this->inject('after-catalog');

            if ($purge) {
                $this->dropOwnedTables($ownedTables, $ownedTablesByModule);
                $this->inject('after-first-drop');
                $this->assertOwnedTablesAbsent($ownedTables);
                $this->pdo->beginTransaction();
                try {
                    $statement = $this->pdo->prepare('DELETE FROM pa_module_migration WHERE module_key IN (' . $this->placeholders($moduleKeys) . ')');
                    $statement->execute($moduleKeys);
                    if ((new ModuleCatalogApplier($this->pdo))->plan($moduleKeys, true)['removed'] !== []) {
                        throw new PluginLifecycleException('MODULE_PURGE_INCOMPLETE', 'Module catalog remains after purge.');
                    }
                    $this->pdo->commit();
                } catch (\Throwable $exception) {
                    if ($this->pdo->inTransaction()) $this->pdo->rollBack();
                    throw $exception;
                }
                $this->inject('after-database-clean');
            }

            $this->finalizeFilesystem($packageKey, $confirmPlan, $confirmPlanDigest, $purge);
            $this->finalizeInstallation($packageKey, $moduleKeys, $purge);
            return [
                'operation' => $purge ? 'purged' : 'retired',
                'package_key' => $packageKey,
                'affected_modules' => $confirmPlan['affected_modules'],
                'removed' => $confirmPlan['removed'],
                'preserved' => $confirmPlan['preserved'],
            ];
        } finally {
            $this->releaseAdvisoryLock($lockName);
        }
    }

    /** @param array{package_key:string,package_manifest_digest:string,descriptor:PluginDescriptor,affected_modules:list<array<string,mixed>>} $scope @return array<string,mixed> */
    private function buildPlan(array $scope, bool $purge): array
    {
        $moduleKeys = array_column($scope['affected_modules'], 'module_key');
        $catalog = (new ModuleCatalogApplier($this->pdo))->plan($moduleKeys, $purge);
        $removed = $catalog['removed'];
        $preserved = $catalog['preserved'];
        $blockers = [...$catalog['blockers'], ...$this->lifecycleBlockers($scope, $purge)];
        $ownedTables = [];
        foreach ($scope['affected_modules'] as $module) {
            foreach ($module['owned_tables'] as $table) $ownedTables[] = $table;
        }
        sort($ownedTables, SORT_STRING);
        if ($purge) {
            $this->appendPlanEntry($removed, 'database', 'owned_tables', 'drop', $this->existingOwnedTables($ownedTables), true);
            $this->appendPlanEntry($removed, 'database', 'pa_module_migration', 'delete', $this->migrationIdentifiers($moduleKeys), true);
        } else {
            $this->appendPlanEntry($preserved, 'database', 'owned_tables', 'preserve', $this->existingOwnedTables($ownedTables), true);
            $this->appendPlanEntry($preserved, 'database', 'pa_module_migration', 'preserve', $this->migrationIdentifiers($moduleKeys), true);
        }
        $this->sortPlanEntries($removed);
        $this->sortPlanEntries($preserved);
        usort($blockers, static fn(array $a, array $b): int => strcmp((string)$a['code'], (string)$b['code']));
        return [
            'schema_version' => 1,
            'package_key' => $scope['package_key'],
            'package_manifest_digest' => $scope['package_manifest_digest'],
            'operation' => $purge ? 'purge' : 'retire',
            'affected_modules' => $scope['affected_modules'],
            'preserved' => $preserved,
            'removed' => $removed,
            'blockers' => $blockers,
        ];
    }

    /** @return array{package_key:string,package_manifest_digest:string,affected_modules:list<array<string,mixed>>}|null */
    private function scope(string $moduleOrPackageKey): ?array
    {
        return $this->lockedScope($moduleOrPackageKey)
            ?? $this->quarantinedScope($moduleOrPackageKey);
    }

    /** @return array{package_key:string,package_manifest_digest:string,descriptor:PluginDescriptor,affected_modules:list<array<string,mixed>>}|null */
    private function lockedScope(string $moduleOrPackageKey): ?array
    {
        $resolver = new PluginLockResolver($this->serverRoot, '../plugins.lock');
        $descriptor = null;
        foreach ($resolver->all() as $plugin) {
            if ($plugin->key === $moduleOrPackageKey || isset($plugin->moduleRoots[$moduleOrPackageKey])) {
                $descriptor = $plugin;
                break;
            }
        }
        if (!$descriptor instanceof PluginDescriptor) return null;
        $affected = [];
        $seenTables = [];
        foreach ($descriptor->moduleRoots as $moduleKey => $root) {
            $manifest = (new ManifestLoader())->load($root);
            $owned = array_values((array)($manifest->data['database']['owned_tables'] ?? []));
            sort($owned, SORT_STRING);
            foreach ($owned as $table) {
                if (!is_string($table) || preg_match('/^pa_[a-z0-9_]+$/D', $table) !== 1 || isset($seenTables[$table])) {
                    throw new PluginLifecycleException('MODULE_TABLE_OWNERSHIP_INVALID', 'Module owned table declaration is invalid.');
                }
                $seenTables[$table] = true;
            }
            $affected[] = [
                'module_key' => $moduleKey,
                'manifest_digest' => $manifest->digest,
                'owned_tables' => $owned,
                'lifecycle_protected' => ModuleLifecyclePolicy::isProtected($manifest),
            ];
        }
        usort($affected, static fn(array $a, array $b): int => strcmp($a['module_key'], $b['module_key']));
        return ['package_key' => $descriptor->key, 'package_manifest_digest' => $descriptor->manifestDigest, 'descriptor' => $descriptor, 'affected_modules' => $affected];
    }

    /** @return array{package_key:string,package_manifest_digest:string,affected_modules:list<array<string,mixed>>}|null */
    private function quarantinedScope(string $moduleOrPackageKey): ?array
    {
        $statement = $this->pdo->prepare(<<<'SQL'
SELECT DISTINCT pi.plugin_key,pi.installed_version,pi.artifact_sha256
FROM pa_plugin_installation pi
JOIN pa_plugin_module pm ON pm.plugin_key=pi.plugin_key
WHERE (pi.plugin_key=:package_input OR pm.module_key=:module_input)
  AND pi.status='uninstalled' AND pi.last_error_code IS NULL
ORDER BY pi.plugin_key
SQL);
        $statement->execute([
            'package_input' => $moduleOrPackageKey,
            'module_input' => $moduleOrPackageKey,
        ]);
        $installation = $statement->fetchAll(PDO::FETCH_ASSOC);
        if ($installation === []) return null;
        if (count($installation) !== 1) {
            throw new PluginLifecycleException('MODULE_QUARANTINE_CONFLICT', 'Retired Module package identity is ambiguous.');
        }

        $packageKey = (string)$installation[0]['plugin_key'];
        $quarantines = $this->quarantineDirectories($packageKey);
        if ($quarantines === []) return null;
        if (count($quarantines) !== 1) {
            throw new PluginLifecycleException('MODULE_QUARANTINE_CONFLICT', 'Retired Module package has multiple quarantine identities.');
        }
        $quarantine = $quarantines[0];
        $pluginManifestPath = $quarantine . '/plugins/' . $packageKey . '/plugin.json';
        try {
            $plugin = json_decode((string)file_get_contents($pluginManifestPath), true, 128, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            throw new PluginLifecycleException('MODULE_QUARANTINE_INVALID', 'Retired Module package manifest is invalid.');
        }
        if (!is_array($plugin) || ($plugin['key'] ?? null) !== $packageKey
            || ($plugin['version'] ?? null) !== $installation[0]['installed_version']
            || ($plugin['source']['sha256'] ?? null) !== $installation[0]['artifact_sha256']
            || !is_array($plugin['modules'] ?? null)) {
            throw new PluginLifecycleException('MODULE_QUARANTINE_INVALID', 'Retired Module package identity changed.');
        }

        $ownership = $this->pdo->prepare(
            'SELECT module_key,manifest_digest FROM pa_plugin_module WHERE plugin_key=? ORDER BY module_key'
        );
        $ownership->execute([$packageKey]);
        $ownedManifests = $ownership->fetchAll(PDO::FETCH_KEY_PAIR);
        $affected = [];
        $seenTables = [];
        $layout = new ModuleHostLayout('server/app/Modules', 'app\\Modules', 'web/src/modules');
        foreach ($plugin['modules'] as $module) {
            if (!is_array($module) || !is_string($module['key'] ?? null) || !is_string($module['root'] ?? null)) {
                throw new PluginLifecycleException('MODULE_QUARANTINE_INVALID', 'Retired Module package scope is invalid.');
            }
            $key = ModuleKey::fromString($module['key']);
            $relativeRoot = rtrim($layout->backendRelativePath($key), '/');
            if ($module['root'] !== $relativeRoot) {
                throw new PluginLifecycleException('MODULE_QUARANTINE_INVALID', 'Retired Module path is not derived from its key.');
            }
            $root = realpath($quarantine . '/' . $relativeRoot);
            if ($root === false || !str_starts_with($root, $quarantine . DIRECTORY_SEPARATOR)) {
                throw new PluginLifecycleException('MODULE_QUARANTINE_INVALID', 'Retired Module source is unavailable.');
            }
            $manifest = (new ManifestLoader())->load($root);
            if (!isset($ownedManifests[$key->value()])
                || !hash_equals((string)$ownedManifests[$key->value()], $manifest->digest)) {
                throw new PluginLifecycleException('MODULE_QUARANTINE_INVALID', 'Retired Module manifest digest changed.');
            }
            $owned = array_values((array)($manifest->data['database']['owned_tables'] ?? []));
            sort($owned, SORT_STRING);
            foreach ($owned as $table) {
                if (!is_string($table) || preg_match('/^pa_[a-z0-9_]+$/D', $table) !== 1 || isset($seenTables[$table])) {
                    throw new PluginLifecycleException('MODULE_TABLE_OWNERSHIP_INVALID', 'Module owned table declaration is invalid.');
                }
                $seenTables[$table] = true;
            }
            $affected[] = [
                'module_key' => $key->value(),
                'manifest_digest' => $manifest->digest,
                'owned_tables' => $owned,
                'lifecycle_protected' => ModuleLifecyclePolicy::isProtected($manifest),
            ];
            unset($ownedManifests[$key->value()]);
        }
        if ($ownedManifests !== []) {
            throw new PluginLifecycleException('MODULE_QUARANTINE_INVALID', 'Retired Module ownership scope changed.');
        }
        usort($affected, static fn(array $a, array $b): int => strcmp($a['module_key'], $b['module_key']));
        $manifestDigest = hash_file('sha256', $pluginManifestPath);
        if (!is_string($manifestDigest)) {
            throw new PluginLifecycleException('MODULE_QUARANTINE_INVALID', 'Retired package manifest cannot be digested.');
        }
        return ['package_key' => $packageKey, 'package_manifest_digest' => $manifestDigest, 'affected_modules' => $affected];
    }

    /** @param array<string,mixed> $scope @return list<array<string,mixed>> */
    private function lifecycleBlockers(array $scope, bool $purge): array
    {
        $moduleKeys = array_column($scope['affected_modules'], 'module_key');
        $blockers = [];
        $protected = [];
        foreach ($scope['affected_modules'] as $module) {
            if (($module['lifecycle_protected'] ?? false) === true) {
                $protected[] = (string)$module['module_key'];
            }
        }
        sort($protected, SORT_STRING);
        if ($protected !== []) {
            $blockers[] = [
                'code' => 'MODULE_LIFECYCLE_PROTECTED',
                'kind' => 'product_policy',
                'identifiers' => $protected,
            ];
        }
        $statement = $this->pdo->prepare('SELECT module_key FROM pa_tenant_module WHERE module_key IN (' . $this->placeholders($moduleKeys) . ") AND status='enabled' ORDER BY module_key");
        $statement->execute($moduleKeys);
        $enabled = array_map('strval', $statement->fetchAll(PDO::FETCH_COLUMN));
        if ($enabled !== []) $blockers[] = ['code' => 'PLUGIN_TENANT_MODULE_ACTIVE', 'kind' => 'tenant_enablement', 'identifiers' => $enabled];

        $dependents = ModuleLifecyclePolicy::activeBusinessDependents(
            $this->pdo,
            new PluginLockResolver(
                $this->serverRoot,
                (string)($this->moduleConfig['plugin_lock'] ?? '../plugins.lock'),
            ),
            $moduleKeys,
        );
        if ($dependents !== []) $blockers[] = ['code' => 'MODULE_DEPENDENT_INSTALLED', 'kind' => 'business_dependency', 'identifiers' => array_values(array_unique($dependents))];

        if ($purge) {
            $owned = [];
            foreach ($scope['affected_modules'] as $module) $owned = [...$owned, ...$module['owned_tables']];
            $external = $this->externalForeignKeys($owned);
            if ($external !== []) $blockers[] = ['code' => 'MODULE_OWNED_TABLE_EXTERNAL_REFERENCE', 'kind' => 'data_integrity', 'identifiers' => $external];
            if ($this->dropOrder($owned) === null) $blockers[] = ['code' => 'MODULE_OWNED_TABLE_FK_CYCLE', 'kind' => 'data_integrity', 'identifiers' => $owned];
        }
        return $blockers;
    }

    /** @param list<string> $tables @return list<string> */
    private function externalForeignKeys(array $tables): array
    {
        if ($tables === []) return [];
        $sql = 'SELECT CONCAT(TABLE_NAME,".",CONSTRAINT_NAME,"->",REFERENCED_TABLE_NAME) FROM information_schema.KEY_COLUMN_USAGE WHERE REFERENCED_TABLE_SCHEMA=DATABASE() AND REFERENCED_TABLE_NAME IN (' . $this->placeholders($tables) . ') AND TABLE_NAME NOT IN (' . $this->placeholders($tables) . ') AND CONSTRAINT_NAME<>"PRIMARY" ORDER BY TABLE_NAME,CONSTRAINT_NAME';
        $statement = $this->pdo->prepare($sql);
        $statement->execute([...$tables, ...$tables]);
        return array_map('strval', $statement->fetchAll(PDO::FETCH_COLUMN));
    }

    /** @param list<string> $tables @return list<string>|null */
    private function dropOrder(array $tables): ?array
    {
        $remaining = array_fill_keys($tables, true);
        if ($tables === []) return [];
        $statement = $this->pdo->prepare('SELECT TABLE_NAME,REFERENCED_TABLE_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA=DATABASE() AND REFERENCED_TABLE_SCHEMA=DATABASE() AND TABLE_NAME IN (' . $this->placeholders($tables) . ') AND REFERENCED_TABLE_NAME IN (' . $this->placeholders($tables) . ')');
        $statement->execute([...$tables, ...$tables]);
        $edges = $statement->fetchAll(PDO::FETCH_NUM);
        $ordered = [];
        while ($remaining !== []) {
            $parents = [];
            foreach ($edges as [$child, $parent]) if (isset($remaining[$child], $remaining[$parent])) $parents[$parent] = true;
            $leaf = null;
            foreach (array_keys($remaining) as $table) if (!isset($parents[$table])) { $leaf = $table; break; }
            if ($leaf === null) return null;
            $ordered[] = $leaf;
            unset($remaining[$leaf]);
        }
        return $ordered;
    }

    /** @param list<string> $tables @param array<string,list<string>> $tablesByModule */
    private function dropOwnedTables(array $tables, array $tablesByModule): void
    {
        $order = $this->dropOrder($tables);
        if ($order === null) throw new PluginLifecycleException('MODULE_OWNED_TABLE_FK_CYCLE', 'Owned table foreign keys contain a cycle.');
        $first = true;
        $moduleBoundaryInjected = false;
        $remainingByModule = [];
        $moduleByTable = [];
        foreach ($tablesByModule as $moduleKey => $moduleTables) {
            $remainingByModule[$moduleKey] = array_fill_keys($moduleTables, true);
            foreach ($moduleTables as $moduleTable) $moduleByTable[$moduleTable] = $moduleKey;
        }
        foreach ($order as $table) {
            if (preg_match('/^pa_[a-z0-9_]+$/D', $table) !== 1) throw new PluginLifecycleException('MODULE_TABLE_OWNERSHIP_INVALID', 'Owned table name is invalid.');
            $this->pdo->exec("DROP TABLE IF EXISTS `{$table}`");
            if ($first) { $this->inject('after-first-drop-statement'); $first = false; }
            $moduleKey = $moduleByTable[$table] ?? null;
            if (is_string($moduleKey)) unset($remainingByModule[$moduleKey][$table]);
            if (is_string($moduleKey) && $remainingByModule[$moduleKey] === []) {
                if (!$moduleBoundaryInjected && count($remainingByModule) > 1) {
                    $moduleBoundaryInjected = true;
                    $this->inject('after-first-module-drop');
                }
                unset($remainingByModule[$moduleKey]);
            }
        }
    }

    /** @param list<string> $tables */
    private function assertOwnedTablesAbsent(array $tables): void
    {
        if ($this->existingOwnedTables($tables) !== []) throw new PluginLifecycleException('MODULE_PURGE_INCOMPLETE', 'Owned tables remain after purge.');
    }

    /** @param list<string> $tables @return list<string> */
    private function existingOwnedTables(array $tables): array
    {
        if ($tables === []) return [];
        $statement = $this->pdo->prepare('SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME IN (' . $this->placeholders($tables) . ') ORDER BY TABLE_NAME');
        $statement->execute($tables);
        return array_map('strval', $statement->fetchAll(PDO::FETCH_COLUMN));
    }

    /** @param list<string> $moduleKeys @return list<string> */
    private function migrationIdentifiers(array $moduleKeys): array
    {
        if ($moduleKeys === []) return [];
        $statement = $this->pdo->prepare('SELECT CONCAT(module_key,"/",migration_key) FROM pa_module_migration WHERE module_key IN (' . $this->placeholders($moduleKeys) . ') ORDER BY module_key,migration_key');
        $statement->execute($moduleKeys);
        return array_map('strval', $statement->fetchAll(PDO::FETCH_COLUMN));
    }

    /** @param list<string> $moduleKeys */
    private function markMaintenance(string $packageKey, array $moduleKeys, string $marker): void
    {
        $now = gmdate('Y-m-d H:i:s.v');
        $this->pdo->beginTransaction();
        try {
            $plugin = $this->pdo->prepare("UPDATE pa_plugin_installation SET status='maintenance',last_error_code=:marker,revision=revision+1,updated_at=:now WHERE plugin_key=:key");
            $plugin->execute(['marker' => $marker, 'now' => $now, 'key' => $packageKey]);
            $modules = $this->pdo->prepare("UPDATE pa_module_installation SET status='maintenance',last_error_code=?,revision=revision+1,updated_at=? WHERE module_key IN (" . $this->placeholders($moduleKeys) . ')');
            $modules->execute([$marker, $now, ...$moduleKeys]);
            $this->pdo->commit();
        } catch (\Throwable $exception) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            throw $exception;
        }
    }

    /** @param list<string> $moduleKeys */
    private function finalizeInstallation(string $packageKey, array $moduleKeys, bool $purge): void
    {
        $now = gmdate('Y-m-d H:i:s.v');
        $this->pdo->beginTransaction();
        try {
            if ($purge) {
                $statement = $this->pdo->prepare('DELETE FROM pa_module_installation WHERE module_key IN (' . $this->placeholders($moduleKeys) . ')');
                $statement->execute($moduleKeys);
            } else {
                $statement = $this->pdo->prepare("UPDATE pa_module_installation SET status='maintenance',last_error_code=NULL,revision=revision+1,updated_at=? WHERE module_key IN (" . $this->placeholders($moduleKeys) . ')');
                $statement->execute([$now, ...$moduleKeys]);
            }
            $plugin = $this->pdo->prepare("UPDATE pa_plugin_installation SET status='uninstalled',last_error_code=NULL,revision=revision+1,uninstalled_at=?,updated_at=? WHERE plugin_key=?");
            $plugin->execute([$now, $now, $packageKey]);
            $this->pdo->commit();
        } catch (\Throwable $exception) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            throw $exception;
        }
    }

    /** @param array<string,mixed> $plan */
    private function finalizeFilesystem(string $packageKey, array $plan, string $digest, bool $purge): void
    {
        $projectRoot = realpath(dirname($this->serverRoot)) ?: dirname($this->serverRoot);
        $lockPath = $projectRoot . '/plugins.lock';
        if (is_file($lockPath)) {
            $lock = json_decode((string)file_get_contents($lockPath), true, 128, JSON_THROW_ON_ERROR);
            if (!is_array($lock) || !is_array($lock['plugins'] ?? null)) throw new PluginLifecycleException('PLUGIN_LOCK_INVALID', 'Plugin lock is invalid.');
            $lock['plugins'] = array_values(array_filter($lock['plugins'], static fn(mixed $entry): bool => !is_array($entry) || ($entry['key'] ?? null) !== $packageKey));
            $contents = json_encode($lock, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n";
            $temporary = $lockPath . '.tmp-' . bin2hex(random_bytes(8));
            $stream = fopen($temporary, 'xb');
            if (!is_resource($stream) || fwrite($stream, $contents) !== strlen($contents) || !fflush($stream)) throw new PluginLifecycleException('PLUGIN_LOCK_WRITE_FAILED', 'Plugin lock cannot be written.');
            if (function_exists('fsync')) fsync($stream);
            fclose($stream);
            if (!rename($temporary, $lockPath)) throw new PluginLifecycleException('PLUGIN_LOCK_WRITE_FAILED', 'Plugin lock cannot be promoted.');
        }
        $this->inject('after-lock');

        $quarantine = $projectRoot . '/.local/module-quarantine/' . $packageKey . '-' . strtolower($digest);
        $paths = ['plugins/' . $packageKey];
        $layout = new ModuleHostLayout('server/app/Modules', 'app\\Modules', 'web/src/modules');
        foreach ($this->confirmedModuleKeys($plan) as $moduleKey) {
            $key = ModuleKey::fromString($moduleKey);
            $paths[] = rtrim($layout->backendRelativePath($key), '/');
            $paths[] = rtrim($layout->frontendRelativePath($key), '/');
        }
        $moved = 0;
        foreach (array_values(array_unique($paths)) as $relative) {
            $source = $projectRoot . '/' . $relative;
            $target = $quarantine . '/' . $relative;
            if (file_exists($source)) {
                if (!is_dir(dirname($target)) && !mkdir(dirname($target), 0700, true) && !is_dir(dirname($target))) throw new PluginLifecycleException('MODULE_QUARANTINE_FAILED', 'Module quarantine cannot be created.');
                if (!rename($source, $target)) throw new PluginLifecycleException('MODULE_QUARANTINE_FAILED', 'Module path cannot enter quarantine.');
                $moved++;
                if ($moved === 1) $this->inject('after-first-quarantine');
            }
        }
        if ($purge) {
            foreach ($this->quarantineDirectories($packageKey) as $directory) {
                $this->removeTree($directory);
            }
        }
    }

    /** @return list<string> */
    private function quarantineDirectories(string $packageKey): array
    {
        if (preg_match('/^[a-z][a-z0-9]*(?:[.-][a-z0-9]+)*$/D', $packageKey) !== 1) {
            throw new PluginLifecycleException('MODULE_QUARANTINE_INVALID', 'Module package key is invalid.');
        }
        $projectRoot = realpath(dirname($this->serverRoot)) ?: dirname($this->serverRoot);
        $root = $projectRoot . '/.local/module-quarantine';
        if (!is_dir($root)) return [];
        $resolvedRoot = realpath($root);
        if (!is_string($resolvedRoot)) {
            throw new PluginLifecycleException('MODULE_QUARANTINE_INVALID', 'Module quarantine root is invalid.');
        }
        $directories = [];
        foreach (glob($root . '/' . $packageKey . '-*', GLOB_ONLYDIR) ?: [] as $candidate) {
            $resolved = realpath($candidate);
            if (is_link($candidate) || !is_string($resolved)
                || !str_starts_with($resolved, $resolvedRoot . DIRECTORY_SEPARATOR)) {
                throw new PluginLifecycleException('MODULE_QUARANTINE_INVALID', 'Module quarantine path is invalid.');
            }
            $directories[] = $resolved;
        }
        sort($directories, SORT_STRING);
        return $directories;
    }

    /** @param array<string,mixed> $plan */
    private function assertResumeScope(array $plan, string $packageKey): void
    {
        $modules = $this->confirmedModuleKeys($plan);
        $statement = $this->pdo->prepare('SELECT module_key,manifest_digest FROM pa_plugin_module WHERE plugin_key=? ORDER BY module_key');
        $statement->execute([$packageKey]);
        $actual = $statement->fetchAll(PDO::FETCH_KEY_PAIR);
        foreach ($plan['affected_modules'] as $module) {
            if (!is_array($module) || !isset($actual[$module['module_key']]) || !hash_equals((string)$actual[$module['module_key']], (string)$module['manifest_digest'])) {
                throw new PluginLifecycleException('MODULE_UNINSTALL_PLAN_CHANGED', 'Module ownership changed during recovery.');
            }
        }
        if (count($actual) !== count($modules)) throw new PluginLifecycleException('MODULE_UNINSTALL_PLAN_CHANGED', 'Package Module scope changed during recovery.');
    }

    /** @param list<array<string,mixed>> $current @param list<array<string,mixed>> $confirmed */
    private function assertCurrentRemovalSubset(array $current, array $confirmed): void
    {
        $allowed = [];
        foreach ($confirmed as $entry) if (is_array($entry)) $allowed[(string)($entry['scope'] ?? '') . "\0" . (string)($entry['table'] ?? '') . "\0" . (string)($entry['action'] ?? '')] = array_fill_keys((array)($entry['identifiers'] ?? []), true);
        foreach ($current as $entry) {
            $key = $entry['scope'] . "\0" . $entry['table'] . "\0" . $entry['action'];
            if (!isset($allowed[$key])) throw new PluginLifecycleException('MODULE_UNINSTALL_PLAN_CHANGED', 'New removal scope appeared during recovery.');
            foreach ($entry['identifiers'] as $identifier) if (!isset($allowed[$key][$identifier])) throw new PluginLifecycleException('MODULE_UNINSTALL_PLAN_CHANGED', 'New removal target appeared during recovery.');
        }
    }

    /** @param array<string,mixed>|null $state @param array<string,mixed> $plan */
    private function isCleanState(?array $state, array $plan, bool $purge): bool
    {
        if (!is_array($state) || $state['status'] !== 'uninstalled' || $state['last_error_code'] !== null) return false;
        if (!$purge) return true;
        $modules = $this->confirmedModuleKeys($plan);
        if ($modules === []) return true;
        $statement = $this->pdo->prepare('SELECT COUNT(*) FROM pa_module_installation WHERE module_key IN (' . $this->placeholders($modules) . ')');
        $statement->execute($modules);
        return (int)$statement->fetchColumn() === 0;
    }

    /** @return array<string,mixed>|null */
    private function installationState(string $packageKey): ?array
    {
        $statement = $this->pdo->prepare('SELECT status,last_error_code FROM pa_plugin_installation WHERE plugin_key=?');
        $statement->execute([$packageKey]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        return is_array($row) ? $row : null;
    }

    private function inputBelongsToPackage(string $input, string $packageKey): bool
    {
        if ($input === $packageKey) return true;
        $statement = $this->pdo->prepare('SELECT COUNT(*) FROM pa_plugin_module WHERE plugin_key=? AND module_key=?');
        $statement->execute([$packageKey, $input]);
        return (int)$statement->fetchColumn() === 1;
    }

    /** @param array<string,mixed> $plan @return list<string> */
    private function confirmedModuleKeys(array $plan): array
    {
        $keys = [];
        foreach ((array)($plan['affected_modules'] ?? []) as $module) {
            $key = is_array($module) ? ($module['module_key'] ?? null) : null;
            if (!is_string($key) || isset($keys[$key])) throw new PluginLifecycleException('MODULE_UNINSTALL_PLAN_CHANGED', 'Confirmed Module scope is invalid.');
            ModuleKey::fromString($key);
            $keys[$key] = true;
        }
        $result = array_keys($keys);
        sort($result, SORT_STRING);
        return $result;
    }

    /** @param array<string,mixed> $plan @return array<string,list<string>> */
    private function confirmedOwnedTablesByModule(array $plan): array
    {
        $modules = [];
        $seen = [];
        foreach ((array)($plan['affected_modules'] ?? []) as $module) {
            $moduleKey = is_array($module) ? ($module['module_key'] ?? null) : null;
            if (!is_string($moduleKey) || isset($modules[$moduleKey])) {
                throw new PluginLifecycleException('MODULE_UNINSTALL_PLAN_CHANGED', 'Confirmed owned table scope is invalid.');
            }
            $tables = [];
            foreach ((array)($module['owned_tables'] ?? []) as $table) {
                if (!is_string($table) || preg_match('/^pa_[a-z0-9_]+$/D', $table) !== 1
                    || isset($tables[$table]) || isset($seen[$table])) {
                    throw new PluginLifecycleException('MODULE_UNINSTALL_PLAN_CHANGED', 'Confirmed owned table scope is invalid.');
                }
                $tables[$table] = true;
                $seen[$table] = true;
            }
            $modules[$moduleKey] = array_keys($tables);
            sort($modules[$moduleKey], SORT_STRING);
        }
        ksort($modules, SORT_STRING);
        return $modules;
    }

    /** @param array<string,list<string>> $tablesByModule @return list<string> */
    private function confirmedOwnedTables(array $tablesByModule): array
    {
        $tables = [];
        foreach ($tablesByModule as $moduleTables) $tables = [...$tables, ...$moduleTables];
        sort($tables, SORT_STRING);
        return $tables;
    }

    /** @return array<string,mixed> */
    private function cleanPlan(string $key, bool $purge): array
    {
        $statement = $this->pdo->prepare('SELECT plugin_key FROM pa_plugin_module WHERE plugin_key=? OR module_key=? ORDER BY plugin_key LIMIT 1');
        $statement->execute([$key, $key]);
        $package = $statement->fetchColumn();
        return ['schema_version' => 1, 'package_key' => is_string($package) ? $package : $key, 'package_manifest_digest' => '', 'operation' => $purge ? 'purge' : 'retire', 'affected_modules' => [], 'preserved' => [], 'removed' => [], 'blockers' => []];
    }

    /** @param list<array<string,mixed>> $entries @param list<string> $identifiers */
    private function appendPlanEntry(array &$entries, string $scope, string $table, string $action, array $identifiers, bool $includeEmpty): void
    {
        sort($identifiers, SORT_STRING);
        if ($identifiers === [] && !$includeEmpty) return;
        $entries[] = ['scope' => $scope, 'table' => $table, 'action' => $action, 'count' => count($identifiers), 'identifiers' => $identifiers];
    }

    /** @param list<array<string,mixed>> $entries */
    private function sortPlanEntries(array &$entries): void
    {
        usort($entries, static fn(array $a, array $b): int => strcmp($a['scope'] . "\0" . $a['table'] . "\0" . $a['action'], $b['scope'] . "\0" . $b['table'] . "\0" . $b['action']));
    }

    private function advisoryLock(string $name): bool
    {
        $statement = $this->pdo->prepare('SELECT GET_LOCK(?,0)');
        $statement->execute([$name]);
        return (int)$statement->fetchColumn() === 1;
    }

    private function releaseAdvisoryLock(string $name): void
    {
        try { $statement = $this->pdo->prepare('SELECT RELEASE_LOCK(?)'); $statement->execute([$name]); } catch (\Throwable) {}
    }

    private function inject(string $point): void
    {
        if (is_callable($this->faultInjector)) ($this->faultInjector)($point);
    }

    private function codec(): ModuleUninstallPlanCodec { return new ModuleUninstallPlanCodec(); }
    /** @param list<mixed> $values */
    private function placeholders(array $values): string { return implode(',', array_fill(0, count($values), '?')); }

    private function removeTree(string $path): void
    {
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($iterator as $entry) $entry->isDir() ? rmdir($entry->getPathname()) : unlink($entry->getPathname());
        rmdir($path);
    }
}
