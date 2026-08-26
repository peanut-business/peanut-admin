<?php
declare(strict_types=1);

require dirname(__DIR__, 2) . '/bootstrap/environment.php';

use app\platform\service\plugin\DeterministicTarArchive;
use app\platform\service\plugin\PluginLifecycleException;
use app\platform\service\plugin\PluginPackageArchiveService;
use app\platform\service\plugin\PluginPackageInstaller;
use app\platform\service\plugin\PluginRuntimeGovernanceService;
use PeanutAdmin\Kernel\Module\ManifestLoader;

require dirname(__DIR__, 2) . '/vendor/autoload.php';
require_once dirname(__DIR__, 2) . '/database/install.php';
require_once dirname(__DIR__) . '/Support/IsolatedBackendEnvironment.php';

function moduleBundleExpect(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function moduleBundleCopyTree(string $source, string $target): void
{
    if (!is_dir($target)) {
        mkdir($target, 0777, true);
    }
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($source, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST,
    );
    foreach ($iterator as $entry) {
        $relative = substr($entry->getPathname(), strlen($source) + 1);
        $destination = $target . '/' . $relative;
        if ($entry->isDir()) {
            if (!is_dir($destination)) mkdir($destination, 0777, true);
        } else {
            copy($entry->getPathname(), $destination);
        }
    }
}

function moduleBundleRemoveTree(string $path): void
{
    if (!is_dir($path)) return;
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST,
    );
    foreach ($iterator as $entry) {
        $entry->isDir() ? rmdir($entry->getPathname()) : unlink($entry->getPathname());
    }
    rmdir($path);
}

/** @return list<string> */
function moduleBundleTables(array $affectedModules): array
{
    $tables = [];
    foreach ($affectedModules as $module) {
        foreach ((array)($module['owned_tables'] ?? []) as $table) $tables[] = (string)$table;
    }
    sort($tables, SORT_STRING);
    return $tables;
}

/** @param list<string> $tables */
function moduleBundleExistingTableCount(PDO $pdo, array $tables): int
{
    if ($tables === []) return 0;
    $statement = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name IN ('
        . implode(',', array_fill(0, count($tables), '?')) . ')'
    );
    $statement->execute($tables);
    return (int)$statement->fetchColumn();
}

/** @param list<string> $moduleKeys */
function moduleBundleCount(PDO $pdo, string $table, array $moduleKeys, ?string $status = null): int
{
    $statement = $pdo->prepare(
        "SELECT COUNT(*) FROM {$table} WHERE module_key IN ("
        . implode(',', array_fill(0, count($moduleKeys), '?')) . ')'
        . ($status === null ? '' : ' AND status=?')
    );
    $statement->execute($status === null ? $moduleKeys : [...$moduleKeys, $status]);
    return (int)$statement->fetchColumn();
}

$database = $argv[1] ?? '';
moduleBundleExpect(
    preg_match('/^peanut_admin_development_p0e_([a-z0-9]{1,11})_plugin_lifecycle$/D', $database, $databaseMatch) === 1,
    'registered isolated plugin-lifecycle database name is required',
);

$host = IsolatedBackendEnvironment::required('DB_HOST');
$port = IsolatedBackendEnvironment::required('DB_PORT');
$user = IsolatedBackendEnvironment::required('DB_USER');
$password = IsolatedBackendEnvironment::required('DB_PASS');
$admin = new PDO(
    "mysql:host={$host};port={$port};charset=utf8mb4",
    $user,
    $password,
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC],
);
$exists = $admin->prepare('SELECT COUNT(*) FROM information_schema.schemata WHERE schema_name=?');
$exists->execute([$database]);
moduleBundleExpect((int)$exists->fetchColumn() === 0, 'isolated bundle database already exists');
$admin->exec("CREATE DATABASE `{$database}` CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci");

IsolatedBackendEnvironment::activate([
    'APP_ENV' => 'development',
    'APP_DEBUG' => 'true',
    'DEPLOYMENT_MODE' => 'standalone',
    'PEANUT_DATABASE_RESOURCE_ID' => 'peanut-admin-p0e-mysql84-gate',
    'PEANUT_DATABASE_ENDPOINT_ID' => 'peanut-admin-p0e-mysql84-gate-host-direct',
    'PEANUT_DATABASE_CONSUMER' => 'host',
    'DB_HOST' => $host,
    'DB_PORT' => $port,
    'DB_NAME' => $database,
    'DB_USER' => $user,
    'DB_PASS' => $password,
    'DB_PREFIX' => 'pa_',
]);

$pdo = new PDO(
    "mysql:host={$host};port={$port};dbname={$database};charset=utf8mb4",
    $user,
    $password,
    [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ],
);
moduleBundleExpect((string)$pdo->query('SELECT DATABASE()')->fetchColumn() === $database, 'isolated database selection changed');
initializeCoreIdentity($pdo, 'module-bundle@example.test', 'module-bundle-test-password', null);
$serverRoot = dirname(__DIR__, 2);
executeSqlFiles($pdo, [$serverRoot . '/database/init.sql']);
$applicationMigrations = glob($serverRoot . '/database/migrations/*.sql') ?: [];
sort($applicationMigrations, SORT_STRING);
executeSqlFiles($pdo, $applicationMigrations);

$projectRoot = dirname($serverRoot);
$temporary = sys_get_temp_dir() . '/pa-module-bundle-' . $databaseMatch[1];
moduleBundleExpect(!file_exists($temporary), 'isolated bundle output path already exists');
$source = $temporary . '/source';
$target = $temporary . '/target';
$archivePath = $temporary . '/official-content-bundle.tar';
$recoverableArchivePath = $temporary . '/official-runtime-bundle.tar';
$completed = false;

try {
    foreach (['Article', 'File', 'Notification', 'Task'] as $module) {
        moduleBundleCopyTree(
            $projectRoot . "/server/app/Modules/Official/{$module}",
            $source . "/server/app/Modules/Official/{$module}",
        );
        $slug = 'official-' . strtolower($module);
        moduleBundleCopyTree($projectRoot . "/web/src/modules/{$slug}", $source . "/web/src/modules/{$slug}");
    }
    foreach ([$source, $target] as $root) {
        if (!is_dir($root . '/server/resources/schemas')) mkdir($root . '/server/resources/schemas', 0777, true);
        copy(
            $serverRoot . '/resources/schemas/plugin.schema.json',
            $root . '/server/resources/schemas/plugin.schema.json',
        );
    }

    $archive = new PluginPackageArchiveService($source . '/server');
    $packed = $archive->packBundle(
        'official-content-bundle',
        '1.0.0',
        ['official.article', 'official.file'],
        $archivePath,
    );
    moduleBundleExpect($packed['modules'] === ['official.article', 'official.file'], 'bundle member order changed');

    $tar = new DeterministicTarArchive();
    $entries = $tar->scan($archivePath);
    $pluginPath = 'plugins/official-content-bundle/plugin.json';
    moduleBundleExpect(isset($entries['META-INF/files.sha256'], $entries[$pluginPath]), 'bundle metadata is incomplete');
    $plugin = json_decode($tar->read($archivePath, $entries[$pluginPath]), true, 64, JSON_THROW_ON_ERROR);
    moduleBundleExpect(($plugin['key'] ?? null) === 'official-content-bundle', 'bundle package key changed');
    moduleBundleExpect(($plugin['key'] ?? null) !== 'official.article' && ($plugin['key'] ?? null) !== 'official.file', 'bundle impersonates a member Module');
    moduleBundleExpect(
        array_column((array)($plugin['modules'] ?? []), 'key') === ['official.article', 'official.file'],
        'bundle plugin manifest lost a member Module',
    );

    $moduleConfig = ['kernel_version' => '1.0.0', 'registered_client_keys' => ['admin-web', 'platform-web']];
    $installer = new PluginPackageInstaller($pdo, $target . '/server', $moduleConfig, []);
    $installed = $installer->install($archivePath, $packed['sha256'], null);
    moduleBundleExpect(($installed['operation'] ?? null) === 'installed', 'bundle was not installed');
    moduleBundleExpect(array_column((array)$installed['modules'], 'module_key') === ['official.article', 'official.file'], 'bundle install returned another scope');
    $unchangedInstall = $installer->install($archivePath, $packed['sha256'], null);
    moduleBundleExpect(($unchangedInstall['operation'] ?? null) === 'unchanged', 'repeated bundle install was not idempotent');

    $moduleKeys = ['official.article', 'official.file'];
    moduleBundleExpect((int)$pdo->query("SELECT COUNT(*) FROM pa_plugin_installation WHERE plugin_key='official-content-bundle' AND status='active'")->fetchColumn() === 1, 'bundle installation row is invalid');
    moduleBundleExpect(moduleBundleCount($pdo, 'pa_module_installation', $moduleKeys, 'active') === 2, 'bundle Module installation rows are invalid');
    moduleBundleExpect((int)$pdo->query("SELECT COUNT(*) FROM pa_plugin_module WHERE plugin_key='official-content-bundle'")->fetchColumn() === 2, 'bundle ownership rows are invalid');
    moduleBundleExpect(moduleBundleCount($pdo, 'pa_tenant_module', $moduleKeys) === 0, 'package install changed TenantModule enablement');

    $catalogTables = [
        'permissions' => 'pa_permission',
        'menus' => 'pa_menu_definition',
        'settings' => 'pa_setting_definition',
    ];
    $catalogExpected = array_fill_keys(array_keys($catalogTables), 0);
    foreach ($moduleKeys as $moduleKey) {
        $root = $target . '/server/app/Modules/Official/' . ($moduleKey === 'official.article' ? 'Article' : 'File');
        $manifest = (new ManifestLoader())->load($root);
        $catalogExpected['permissions'] += count((array)($manifest->data['catalog']['permissions'] ?? []));
        $catalogExpected['menus'] += count((array)($manifest->data['catalog']['menus'] ?? []));
        $definitions = json_decode((string)file_get_contents($root . '/Resources/setting-definitions.json'), true, 64, JSON_THROW_ON_ERROR);
        $catalogExpected['settings'] += count((array)$definitions);
    }
    foreach ($catalogTables as $name => $table) {
        moduleBundleExpect(moduleBundleCount($pdo, $table, $moduleKeys, 'active') === $catalogExpected[$name], "bundle {$name} catalog is not active");
    }

    $governance = new PluginRuntimeGovernanceService($pdo, $target . '/server', $moduleConfig);
    $retirePreview = $governance->preview('official.article', false);
    moduleBundleExpect(($retirePreview['confirm_plan']['package_key'] ?? null) === 'official-content-bundle', 'member key did not resolve the bundle package');
    moduleBundleExpect(array_column($retirePreview['affected_modules'], 'module_key') === $moduleKeys, 'retire preview did not display the complete bundle scope');
    moduleBundleExpect($retirePreview['blockers'] === [], 'bundle retire preview unexpectedly blocked');
    $ownedTables = moduleBundleTables($retirePreview['affected_modules']);
    $migrationCount = moduleBundleCount($pdo, 'pa_module_migration', $moduleKeys);
    moduleBundleExpect($migrationCount > 0, 'bundle install did not apply Module migrations');
    moduleBundleExpect(moduleBundleExistingTableCount($pdo, $ownedTables) === count($ownedTables), 'bundle owned table baseline is incomplete');

    $retired = $governance->uninstall('official.article', false, $retirePreview['confirm_plan'], $retirePreview['plan_digest']);
    moduleBundleExpect(($retired['operation'] ?? null) === 'retired', 'bundle retire failed');
    moduleBundleExpect(array_column($retired['affected_modules'], 'module_key') === $moduleKeys, 'bundle retire split its member scope');
    moduleBundleExpect(moduleBundleExistingTableCount($pdo, $ownedTables) === count($ownedTables), 'bundle retire deleted owned tables');
    moduleBundleExpect(moduleBundleCount($pdo, 'pa_module_migration', $moduleKeys) === $migrationCount, 'bundle retire deleted migration ledger');
    foreach ($catalogTables as $table) {
        moduleBundleExpect(moduleBundleCount($pdo, $table, $moduleKeys, 'active') === 0, 'bundle retire left active catalog rows');
    }
    $repeatRetire = $governance->uninstall('official.article', false, $retirePreview['confirm_plan'], $retirePreview['plan_digest']);
    moduleBundleExpect(($repeatRetire['operation'] ?? null) === 'unchanged', 'repeated bundle retire was not idempotent');

    $purgePreview = (new PluginRuntimeGovernanceService($pdo, $target . '/server', $moduleConfig))->preview('official.file', true);
    moduleBundleExpect(($purgePreview['confirm_plan']['package_key'] ?? null) === 'official-content-bundle', 'retired member key lost the bundle package');
    moduleBundleExpect(array_column($purgePreview['affected_modules'], 'module_key') === $moduleKeys, 'purge preview did not display the complete bundle scope');
    $externalReferenceBlockers = array_values(array_filter(
        $purgePreview['blockers'],
        static fn(array $blocker): bool => ($blocker['code'] ?? null) === 'MODULE_OWNED_TABLE_EXTERNAL_REFERENCE',
    ));
    moduleBundleExpect(count($externalReferenceBlockers) === 1, 'content bundle purge did not expose its external file references');
    moduleBundleExpect(
        in_array(
            'pa_customer_service_setting.fk_customer_service_setting_qr_file->pa_file',
            (array)$externalReferenceBlockers[0]['identifiers'],
            true,
        ),
        'content bundle purge lost the customer-service file reference blocker',
    );
    try {
        (new PluginRuntimeGovernanceService($pdo, $target . '/server', $moduleConfig))
            ->uninstall('official.file', true, $purgePreview['confirm_plan'], $purgePreview['plan_digest']);
        throw new RuntimeException('blocked content bundle purge unexpectedly executed');
    } catch (PluginLifecycleException $exception) {
        moduleBundleExpect($exception->errorCode === 'MODULE_UNINSTALL_BLOCKED', 'content bundle purge returned another blocker error');
    }
    moduleBundleExpect(moduleBundleExistingTableCount($pdo, $ownedTables) === count($ownedTables), 'blocked content bundle purge changed owned tables');
    moduleBundleExpect(moduleBundleCount($pdo, 'pa_module_migration', $moduleKeys) === $migrationCount, 'blocked content bundle purge changed migration ledger');

    $recoverablePacked = $archive->packBundle(
        'official-runtime-bundle',
        '1.0.0',
        ['official.notification', 'official.task'],
        $recoverableArchivePath,
    );
    $recoverableModuleKeys = ['official.notification', 'official.task'];
    moduleBundleExpect($recoverablePacked['modules'] === $recoverableModuleKeys, 'recoverable bundle member order changed');
    $recoverableInstalled = $installer->install($recoverableArchivePath, $recoverablePacked['sha256'], null);
    moduleBundleExpect(($recoverableInstalled['operation'] ?? null) === 'installed', 'recoverable bundle was not installed');
    moduleBundleExpect(
        array_column((array)$recoverableInstalled['modules'], 'module_key') === $recoverableModuleKeys,
        'recoverable bundle install returned another scope',
    );
    $recoverableUnchanged = $installer->install($recoverableArchivePath, $recoverablePacked['sha256'], null);
    moduleBundleExpect(($recoverableUnchanged['operation'] ?? null) === 'unchanged', 'repeated recoverable bundle install was not idempotent');
    moduleBundleExpect((int)$pdo->query("SELECT COUNT(*) FROM pa_plugin_installation WHERE plugin_key='official-runtime-bundle' AND status='active'")->fetchColumn() === 1, 'recoverable bundle installation row is invalid');
    moduleBundleExpect(moduleBundleCount($pdo, 'pa_module_installation', $recoverableModuleKeys, 'active') === 2, 'recoverable bundle Module installation rows are invalid');
    moduleBundleExpect((int)$pdo->query("SELECT COUNT(*) FROM pa_plugin_module WHERE plugin_key='official-runtime-bundle'")->fetchColumn() === 2, 'recoverable bundle ownership rows are invalid');
    moduleBundleExpect(moduleBundleCount($pdo, 'pa_tenant_module', $recoverableModuleKeys) === 0, 'recoverable package install changed TenantModule enablement');

    $recoverableCatalogExpected = array_fill_keys(array_keys($catalogTables), 0);
    $recoverableDirectories = ['official.notification' => 'Notification', 'official.task' => 'Task'];
    foreach ($recoverableModuleKeys as $moduleKey) {
        $root = $target . '/server/app/Modules/Official/' . $recoverableDirectories[$moduleKey];
        $manifest = (new ManifestLoader())->load($root);
        $recoverableCatalogExpected['permissions'] += count((array)($manifest->data['catalog']['permissions'] ?? []));
        $recoverableCatalogExpected['menus'] += count((array)($manifest->data['catalog']['menus'] ?? []));
        $definitions = json_decode((string)file_get_contents($root . '/Resources/setting-definitions.json'), true, 64, JSON_THROW_ON_ERROR);
        $recoverableCatalogExpected['settings'] += count((array)$definitions);
    }
    foreach ($catalogTables as $name => $table) {
        moduleBundleExpect(
            moduleBundleCount($pdo, $table, $recoverableModuleKeys, 'active') === $recoverableCatalogExpected[$name],
            "recoverable bundle {$name} catalog is not active",
        );
    }

    $recoverableGovernance = new PluginRuntimeGovernanceService($pdo, $target . '/server', $moduleConfig);
    $recoverableRetirePreview = $recoverableGovernance->preview('official.task', false);
    moduleBundleExpect(($recoverableRetirePreview['confirm_plan']['package_key'] ?? null) === 'official-runtime-bundle', 'recoverable member key did not resolve its bundle');
    moduleBundleExpect(array_column($recoverableRetirePreview['affected_modules'], 'module_key') === $recoverableModuleKeys, 'recoverable retire preview split its bundle scope');
    moduleBundleExpect($recoverableRetirePreview['blockers'] === [], 'recoverable bundle retire preview unexpectedly blocked');
    $recoverableOwnedTables = moduleBundleTables($recoverableRetirePreview['affected_modules']);
    $recoverableMigrationCount = moduleBundleCount($pdo, 'pa_module_migration', $recoverableModuleKeys);
    moduleBundleExpect($recoverableMigrationCount > 0, 'recoverable bundle did not apply Module migrations');
    moduleBundleExpect(moduleBundleExistingTableCount($pdo, $recoverableOwnedTables) === count($recoverableOwnedTables), 'recoverable bundle owned table baseline is incomplete');

    $recoverableRetired = $recoverableGovernance->uninstall(
        'official.task',
        false,
        $recoverableRetirePreview['confirm_plan'],
        $recoverableRetirePreview['plan_digest'],
    );
    moduleBundleExpect(($recoverableRetired['operation'] ?? null) === 'retired', 'recoverable bundle retire failed');
    moduleBundleExpect(moduleBundleExistingTableCount($pdo, $recoverableOwnedTables) === count($recoverableOwnedTables), 'recoverable bundle retire deleted owned tables');
    moduleBundleExpect(moduleBundleCount($pdo, 'pa_module_migration', $recoverableModuleKeys) === $recoverableMigrationCount, 'recoverable bundle retire deleted migration ledger');
    foreach ($catalogTables as $table) {
        moduleBundleExpect(moduleBundleCount($pdo, $table, $recoverableModuleKeys, 'active') === 0, 'recoverable bundle retire left active catalog rows');
    }
    $recoverableRepeatRetire = $recoverableGovernance->uninstall(
        'official.task',
        false,
        $recoverableRetirePreview['confirm_plan'],
        $recoverableRetirePreview['plan_digest'],
    );
    moduleBundleExpect(($recoverableRepeatRetire['operation'] ?? null) === 'unchanged', 'repeated recoverable bundle retire was not idempotent');

    $recoverablePurgePreview = (new PluginRuntimeGovernanceService($pdo, $target . '/server', $moduleConfig))
        ->preview('official.notification', true);
    moduleBundleExpect(($recoverablePurgePreview['confirm_plan']['package_key'] ?? null) === 'official-runtime-bundle', 'retired recoverable member key lost its bundle');
    moduleBundleExpect(array_column($recoverablePurgePreview['affected_modules'], 'module_key') === $recoverableModuleKeys, 'recoverable purge preview split its bundle scope');
    moduleBundleExpect($recoverablePurgePreview['blockers'] === [], 'recoverable bundle purge preview unexpectedly blocked');

    try {
        (new PluginRuntimeGovernanceService(
            $pdo,
            $target . '/server',
            $moduleConfig,
            static function (string $point): void {
                if ($point === 'after-first-module-drop') throw new RuntimeException('injected bundle interruption');
            },
        ))->uninstall(
            'official.notification',
            true,
            $recoverablePurgePreview['confirm_plan'],
            $recoverablePurgePreview['plan_digest'],
        );
        throw new RuntimeException('bundle purge interruption was not injected');
    } catch (RuntimeException $exception) {
        moduleBundleExpect($exception->getMessage() === 'injected bundle interruption', 'unexpected bundle purge interruption');
    }
    $state = $pdo->query("SELECT status,last_error_code FROM pa_plugin_installation WHERE plugin_key='official-runtime-bundle'")->fetch();
    moduleBundleExpect($state === ['status' => 'maintenance', 'last_error_code' => 'MODULE_PURGE_IN_PROGRESS'], 'interrupted bundle purge marker changed');
    $interruptedModuleTableCounts = [];
    foreach ($recoverablePurgePreview['affected_modules'] as $module) {
        $interruptedModuleTableCounts[] = moduleBundleExistingTableCount($pdo, moduleBundleTables([$module]));
    }
    sort($interruptedModuleTableCounts, SORT_NUMERIC);
    moduleBundleExpect($interruptedModuleTableCounts[0] === 0 && $interruptedModuleTableCounts[1] > 0, 'bundle interruption did not stop between member completion points');
    moduleBundleExpect(moduleBundleCount($pdo, 'pa_module_migration', $recoverableModuleKeys) === $recoverableMigrationCount, 'interrupted bundle purge deleted migration ledger early');

    $purged = (new PluginRuntimeGovernanceService($pdo, $target . '/server', $moduleConfig))
        ->uninstall(
            'official.notification',
            true,
            $recoverablePurgePreview['confirm_plan'],
            $recoverablePurgePreview['plan_digest'],
        );
    moduleBundleExpect(($purged['operation'] ?? null) === 'purged', 'bundle purge recovery did not finish');
    moduleBundleExpect(moduleBundleExistingTableCount($pdo, $recoverableOwnedTables) === 0, 'bundle purge left owned tables');
    moduleBundleExpect(moduleBundleCount($pdo, 'pa_module_migration', $recoverableModuleKeys) === 0, 'bundle purge left migration ledger');
    foreach ($catalogTables as $table) {
        moduleBundleExpect(moduleBundleCount($pdo, $table, $recoverableModuleKeys) === 0, 'bundle purge left catalog rows');
    }
    moduleBundleExpect(moduleBundleCount($pdo, 'pa_module_installation', $recoverableModuleKeys) === 0, 'bundle purge left Module installation rows');
    moduleBundleExpect((int)$pdo->query("SELECT COUNT(*) FROM pa_plugin_module WHERE plugin_key='official-runtime-bundle'")->fetchColumn() === 2, 'bundle purge deleted ownership history');
    $repeatPurge = (new PluginRuntimeGovernanceService($pdo, $target . '/server', $moduleConfig))
        ->uninstall(
            'official.notification',
            true,
            $recoverablePurgePreview['confirm_plan'],
            $recoverablePurgePreview['plan_digest'],
        );
    moduleBundleExpect(($repeatPurge['operation'] ?? null) === 'unchanged', 'repeated bundle purge was not idempotent');

    $completed = true;
    echo "MODULE-BUNDLE-LIFECYCLE-001 passed database={$database} content_sha256={$packed['sha256']} recoverable_sha256={$recoverablePacked['sha256']}\n";
} finally {
    moduleBundleRemoveTree($temporary);
    IsolatedBackendEnvironment::cleanup();
    $pdo = null;
    if ($completed) {
        $admin->exec("DROP DATABASE `{$database}`");
    } else {
        fwrite(STDERR, "BUNDLE_TEST_DATABASE_RETAINED={$database}\n");
    }
}
