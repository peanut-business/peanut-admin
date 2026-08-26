<?php
declare(strict_types=1);

use app\platform\service\plugin\ModuleUninstallPlanCodec;
use app\platform\service\plugin\PluginArtifactWriter;
use app\platform\service\plugin\PluginLifecycleException;
use app\platform\service\plugin\PluginLockResolver;
use app\platform\service\plugin\PluginRuntimeGovernanceService;
use PeanutAdmin\Kernel\Module\ManifestLoader;

require dirname(__DIR__, 2) . '/vendor/autoload.php';
require_once dirname(__DIR__, 2) . '/database/install.php';

function moduleGovernanceExpect(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}

function moduleGovernanceCopyTree(string $source, string $target): void
{
    if (!is_dir($target)) mkdir($target, 0777, true);
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($source, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::SELF_FIRST);
    foreach ($iterator as $entry) {
        $relative = substr($entry->getPathname(), strlen($source) + 1);
        $destination = $target . '/' . $relative;
        if ($entry->isDir()) { if (!is_dir($destination)) mkdir($destination, 0777, true); }
        else copy($entry->getPathname(), $destination);
    }
}

function moduleGovernanceRemoveTree(string $path): void
{
    if (!is_dir($path)) return;
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($iterator as $entry) $entry->isDir() ? rmdir($entry->getPathname()) : unlink($entry->getPathname());
    rmdir($path);
}

function moduleGovernanceProject(string $sourceRoot, string $targetRoot): void
{
    moduleGovernanceCopyTree($sourceRoot . '/server/app/Modules/Fixture/DeliveryRecord', $targetRoot . '/server/app/Modules/Fixture/DeliveryRecord');
    moduleGovernanceCopyTree($sourceRoot . '/web/src/modules/fixture-delivery-record', $targetRoot . '/web/src/modules/fixture-delivery-record');
    if (!is_dir($targetRoot . '/server/resources/schemas')) mkdir($targetRoot . '/server/resources/schemas', 0777, true);
    copy($sourceRoot . '/server/resources/schemas/plugin.schema.json', $targetRoot . '/server/resources/schemas/plugin.schema.json');
    $manifestPath = $targetRoot . '/server/app/Modules/Fixture/DeliveryRecord/module.json';
    $manifest = json_decode((string)file_get_contents($manifestPath), true, 64, JSON_THROW_ON_ERROR);
    $manifest['database']['owned_tables'] = ['pa_fixture_delivery_record', 'pa_fixture_delivery_aux'];
    file_put_contents($manifestPath, json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n");
    $writer = new PluginArtifactWriter($targetRoot . '/server');
    $writer->make('fixture.delivery-record', '1.0.0', ['fixture.delivery-record=server/app/Modules/Fixture/DeliveryRecord']);
    $writer->writeLock();
}

function moduleGovernanceSeed(PDO $pdo, string $projectRoot): array
{
    $descriptor = (new PluginLockResolver($projectRoot . '/server', '../plugins.lock'))->require('fixture.delivery-record');
    $manifest = (new ManifestLoader())->load($descriptor->moduleRoots['fixture.delivery-record']);
    $now = gmdate('Y-m-d H:i:s.v');
    $pdo->exec('CREATE TABLE IF NOT EXISTS pa_fixture_delivery_record (id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY, value VARCHAR(64) NOT NULL) ENGINE=InnoDB');
    $pdo->exec('CREATE TABLE IF NOT EXISTS pa_fixture_delivery_aux (id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY, record_id BIGINT UNSIGNED NOT NULL, CONSTRAINT fk_fixture_aux_record FOREIGN KEY (record_id) REFERENCES pa_fixture_delivery_record(id) ON DELETE RESTRICT) ENGINE=InnoDB');
    $pdo->exec("INSERT INTO pa_fixture_delivery_record(value) VALUES ('preserved')");
    $recordId = (int)$pdo->lastInsertId();
    $statement = $pdo->prepare('INSERT INTO pa_fixture_delivery_aux(record_id) VALUES (?)');
    $statement->execute([$recordId]);

    $plugin = $pdo->prepare(<<<'SQL'
INSERT INTO pa_plugin_installation (
 plugin_key,installed_version,source,artifact_sha256,lock_digest,composer_identity_json,npm_identity_json,
 frontend_identity_json,status,revision,installed_at,activated_at,created_at,updated_at
) VALUES (?,?,?,?,?,?,?,?,'active',1,?,?,?,?)
ON DUPLICATE KEY UPDATE status='active',last_error_code=NULL,uninstalled_at=NULL,lock_digest=VALUES(lock_digest),revision=revision+1,updated_at=VALUES(updated_at)
SQL);
    $plugin->execute([
        $descriptor->key, $descriptor->version, $descriptor->source['type'] . ':' . $descriptor->source['reference'],
        $descriptor->source['sha256'], $descriptor->lockDigest,
        json_encode($descriptor->composer, JSON_THROW_ON_ERROR), json_encode($descriptor->npm, JSON_THROW_ON_ERROR),
        json_encode($descriptor->frontend, JSON_THROW_ON_ERROR), $now, $now, $now, $now,
    ]);
    $module = $pdo->prepare(<<<'SQL'
INSERT INTO pa_module_installation (module_key,installed_version,manifest_schema_version,manifest_digest,status,revision,installed_at,activated_at,created_at,updated_at)
VALUES (?,?,1,?,'active',1,?,?,?,?)
ON DUPLICATE KEY UPDATE status='active',last_error_code=NULL,manifest_digest=VALUES(manifest_digest),revision=revision+1,updated_at=VALUES(updated_at)
SQL);
    $module->execute(['fixture.delivery-record', '1.0.0', $manifest->digest, $now, $now, $now, $now]);
    $ownership = $pdo->prepare(<<<'SQL'
INSERT INTO pa_plugin_module (plugin_key,module_key,module_version,manifest_digest,created_at,updated_at)
VALUES ('fixture.delivery-record','fixture.delivery-record','1.0.0',?,?,?)
ON DUPLICATE KEY UPDATE manifest_digest=VALUES(manifest_digest),updated_at=VALUES(updated_at)
SQL);
    $ownership->execute([$manifest->digest, $now, $now]);
    $migration = $pdo->prepare(<<<'SQL'
INSERT INTO pa_module_migration (module_key,migration_key,module_version,checksum,batch_no,status,started_at,finished_at)
VALUES ('fixture.delivery-record','20260814000000_create_delivery_record','1.0.0',?,1,'applied',?,?)
ON DUPLICATE KEY UPDATE checksum=VALUES(checksum),status='applied',finished_at=VALUES(finished_at),error_code=NULL
SQL);
    $migration->execute([hash('sha256', 'fixture migration'), $now, $now]);
    $permission = $pdo->prepare(<<<'SQL'
INSERT INTO pa_permission (`key`,module_key,type,name,description,risk_level,status,manifest_version,created_at,updated_at,retired_at)
VALUES ('fixture.delivery-record.create','fixture.delivery-record','action','Create delivery record','fixture','normal','active','1.0.0',?,?,NULL)
ON DUPLICATE KEY UPDATE module_key=VALUES(module_key),status='active',retired_at=NULL,updated_at=VALUES(updated_at)
SQL);
    $permission->execute([$now, $now]);
    $permissionId = (int)$pdo->query("SELECT id FROM pa_permission WHERE `key`='fixture.delivery-record.create'")->fetchColumn();
    $menu = $pdo->prepare(<<<'SQL'
INSERT INTO pa_menu_definition (`key`,module_key,scope,parent_key,type,name,route_name,route_path,component_key,icon,sort_order,required_permission_id,client_keys_json,status,manifest_digest,created_at,updated_at)
VALUES ('fixture.delivery-record.menu','fixture.delivery-record','tenant',NULL,'page','Fixture','fixture-delivery-record','/fixture-delivery-record','fixture.delivery-record.list',NULL,1,?,'["admin-web"]','active',?,?,?)
ON DUPLICATE KEY UPDATE required_permission_id=VALUES(required_permission_id),status='active',manifest_digest=VALUES(manifest_digest),updated_at=VALUES(updated_at)
SQL);
    $menu->execute([$permissionId, $manifest->digest, $now, $now]);
    $role = $pdo->query("SELECT tenant_id,id FROM pa_role WHERE status='active' ORDER BY id LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    moduleGovernanceExpect(is_array($role), 'active tenant role is unavailable');
    $binding = $pdo->prepare('INSERT IGNORE INTO pa_role_permission (tenant_id,role_id,permission_id,granted_at) VALUES (?,?,?,?)');
    $binding->execute([(int)$role['tenant_id'], (int)$role['id'], $permissionId, $now]);
    $platformRole = $pdo->query("SELECT id FROM pa_platform_role WHERE status='active' ORDER BY id LIMIT 1")->fetchColumn();
    if ($platformRole !== false) {
        $binding = $pdo->prepare('INSERT IGNORE INTO pa_platform_role_permission (platform_role_id,permission_id,granted_at) VALUES (?,?,?)');
        $binding->execute([(int)$platformRole, $permissionId, $now]);
    }
    return ['permission_id' => $permissionId, 'manifest_digest' => $manifest->digest];
}

$host = getenv('DB_HOST') ?: '';
$port = getenv('DB_PORT') ?: '';
$database = getenv('DB_NAME') ?: '';
$user = getenv('DB_USER') ?: '';
$password = getenv('DB_PASS') ?: '';
moduleGovernanceExpect($host !== '' && $port !== '' && $database !== '' && $user !== '', 'registered database environment is required');
$pdo = new PDO("mysql:host={$host};port={$port};dbname={$database};charset=utf8mb4", $user, $password, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, PDO::ATTR_EMULATE_PREPARES => false]);
moduleGovernanceExpect((int)$pdo->query('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE()')->fetchColumn() === 0, 'governance test database must start empty');
initializeCoreIdentity($pdo, 'module-governance@example.test', 'module-governance-test-password', null);
executeSqlFiles($pdo, [dirname(__DIR__, 2) . '/database/init.sql']);

$sourceRoot = dirname(__DIR__, 3);
$temporary = sys_get_temp_dir() . '/pa-module-governance-' . bin2hex(random_bytes(8));
mkdir($temporary, 0700, true);
try {
    moduleGovernanceProject($sourceRoot, $temporary);
    moduleGovernanceSeed($pdo, $temporary);
    $service = new PluginRuntimeGovernanceService($pdo, $temporary . '/server', []);
    $retirePreview = $service->preview('fixture.delivery-record', false);
    moduleGovernanceExpect($retirePreview['blockers'] === [], 'retire preview unexpectedly blocked');
    try {
        $service->uninstall('fixture.delivery-record', false, $retirePreview['confirm_plan'], str_repeat('0', 64));
        throw new RuntimeException('invalid retire plan digest was accepted');
    } catch (PluginLifecycleException $exception) {
        moduleGovernanceExpect($exception->errorCode === 'MODULE_UNINSTALL_PLAN_CHANGED', 'invalid plan digest error changed');
    }
    moduleGovernanceExpect((string)$pdo->query("SELECT status FROM pa_plugin_installation WHERE plugin_key='fixture.delivery-record'")->fetchColumn() === 'active', 'invalid plan digest mutated installation state');
    $tenantId = (int)$pdo->query("SELECT id FROM pa_tenant WHERE status='active' ORDER BY id LIMIT 1")->fetchColumn();
    $now = gmdate('Y-m-d H:i:s.v');
    $enabled = $pdo->prepare(<<<'SQL'
INSERT INTO pa_tenant_module (tenant_id,module_key,status,source,config_json,config_revision,authorization_revision,effective_at,enabled_at,created_at,updated_at)
VALUES (?,'fixture.delivery-record','enabled','manual','{}',1,1,?,?,?,?)
SQL);
    $enabled->execute([$tenantId, $now, $now, $now, $now]);
    $blocked = $service->preview('fixture.delivery-record', false);
    moduleGovernanceExpect(in_array('PLUGIN_TENANT_MODULE_ACTIVE', array_column($blocked['blockers'], 'code'), true), 'enabled TenantModule did not block retire preview');
    $pdo->exec("DELETE FROM pa_tenant_module WHERE module_key='fixture.delivery-record'");
    $retirePreview = $service->preview('fixture.delivery-record', false);
    $retire = $service->uninstall('fixture.delivery-record', false, $retirePreview['confirm_plan'], $retirePreview['plan_digest']);
    moduleGovernanceExpect($retire['operation'] === 'retired', 'default uninstall did not retire');
    moduleGovernanceExpect((int)$pdo->query('SELECT COUNT(*) FROM pa_fixture_delivery_record')->fetchColumn() === 1, 'retire deleted business data');
    moduleGovernanceExpect((int)$pdo->query("SELECT COUNT(*) FROM pa_module_migration WHERE module_key='fixture.delivery-record'")->fetchColumn() === 1, 'retire deleted migration ledger');
    moduleGovernanceExpect((int)$pdo->query("SELECT COUNT(*) FROM pa_role_permission rp JOIN pa_permission p ON p.id=rp.permission_id WHERE p.module_key='fixture.delivery-record'")->fetchColumn() === 1, 'retire deleted tenant role binding');
    moduleGovernanceExpect((string)$pdo->query("SELECT status FROM pa_permission WHERE module_key='fixture.delivery-record'")->fetchColumn() === 'retired', 'retire left permission active');

    moduleGovernanceProject($sourceRoot, $temporary);
    $purgeSeed = moduleGovernanceSeed($pdo, $temporary);
    $previewA = (new PluginRuntimeGovernanceService($pdo, $temporary . '/server', []))->preview('fixture.delivery-record', true);
    $previewB = (new PluginRuntimeGovernanceService($pdo, $temporary . '/server', []))->preview('fixture.delivery-record', true);
    moduleGovernanceExpect($previewA['plan_digest'] === $previewB['plan_digest'], 'purge preview digest is not deterministic');
    moduleGovernanceExpect($previewA['blockers'] === [], 'purge preview unexpectedly blocked');
    moduleGovernanceExpect(count(array_filter($previewA['removed'], static fn(array $entry): bool => in_array($entry['table'], ['pa_role_permission', 'pa_platform_role_permission'], true))) === 2, 'purge preview omitted explicit role bindings');
    try {
        (new PluginRuntimeGovernanceService($pdo, $temporary . '/server', [], static function (string $point): void {
            if ($point === 'after-first-drop-statement') throw new RuntimeException('injected interruption');
        }))->uninstall('fixture.delivery-record', true, $previewA['confirm_plan'], $previewA['plan_digest']);
        throw new RuntimeException('purge interruption was not injected');
    } catch (RuntimeException $exception) {
        moduleGovernanceExpect($exception->getMessage() === 'injected interruption', 'unexpected purge interruption');
    }
    $state = $pdo->query("SELECT status,last_error_code FROM pa_plugin_installation WHERE plugin_key='fixture.delivery-record'")->fetch();
    moduleGovernanceExpect($state === ['status' => 'maintenance', 'last_error_code' => 'MODULE_PURGE_IN_PROGRESS'], 'interrupted purge marker changed');
    moduleGovernanceExpect((int)$pdo->query("SELECT COUNT(*) FROM pa_module_migration WHERE module_key='fixture.delivery-record'")->fetchColumn() === 1, 'interrupted purge cleared ledger before all tables');
    $remainingTables = (int)$pdo->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name IN ('pa_fixture_delivery_record','pa_fixture_delivery_aux')")->fetchColumn();
    moduleGovernanceExpect($remainingTables === 1, 'interrupted purge did not leave a deterministic partial table set');

    $purged = (new PluginRuntimeGovernanceService($pdo, $temporary . '/server', []))->uninstall('fixture.delivery-record', true, $previewA['confirm_plan'], $previewA['plan_digest']);
    moduleGovernanceExpect($purged['operation'] === 'purged', 'purge recovery did not finish');
    moduleGovernanceExpect((int)$pdo->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name IN ('pa_fixture_delivery_record','pa_fixture_delivery_aux')")->fetchColumn() === 0, 'purge left owned tables');
    moduleGovernanceExpect((int)$pdo->query("SELECT COUNT(*) FROM pa_module_migration WHERE module_key='fixture.delivery-record'")->fetchColumn() === 0, 'purge left migration ledger');
    moduleGovernanceExpect((int)$pdo->query("SELECT COUNT(*) FROM pa_permission WHERE module_key='fixture.delivery-record'")->fetchColumn() === 0, 'purge left catalog permission');
    $bindingCount = $pdo->prepare('SELECT COUNT(*) FROM pa_role_permission WHERE permission_id=?');
    $bindingCount->execute([$purgeSeed['permission_id']]);
    moduleGovernanceExpect((int)$bindingCount->fetchColumn() === 0, 'purge left tenant role binding');
    $bindingCount = $pdo->prepare('SELECT COUNT(*) FROM pa_platform_role_permission WHERE permission_id=?');
    $bindingCount->execute([$purgeSeed['permission_id']]);
    moduleGovernanceExpect((int)$bindingCount->fetchColumn() === 0, 'purge left platform role binding');
    $unchanged = (new PluginRuntimeGovernanceService($pdo, $temporary . '/server', []))->uninstall('fixture.delivery-record', true, $previewA['confirm_plan'], $previewA['plan_digest']);
    moduleGovernanceExpect($unchanged['operation'] === 'unchanged', 'repeated clean purge was not idempotent');

    $codec = new ModuleUninstallPlanCodec();
    moduleGovernanceExpect($codec->digest(['b' => 2, 'a' => 1]) === $codec->digest(['a' => 1, 'b' => 2]), 'plan object key ordering changes digest');
    echo "MODULE-RUNTIME-GOVERNANCE-D2-001 passed plan_digest={$previewA['plan_digest']}\n";
} finally {
    moduleGovernanceRemoveTree($temporary);
}
