<?php
declare(strict_types=1);

use app\adminapi\logic\file\FileCateLogic;
use app\adminapi\logic\file\FileLogic;
use app\common\enum\FileEnum;
use app\common\service\file\FileObjectNamespace;
use app\common\service\file\FileTenantContext;
use app\common\service\file\FileTenantRepository;
use PeanutAdmin\Kernel\Auth\TenantContext;
use PeanutAdmin\Kernel\Auth\ValidatedTenantSession;

require dirname(__DIR__, 2) . '/vendor/autoload.php';

function expectFileTenant(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function fileTenantContext(int $tenantId, int $memberId, string $requestId): TenantContext
{
    return TenantContext::fromValidatedSession(new ValidatedTenantSession(
        $memberId,
        '01JMT03FILEOBJ' . str_pad((string)$memberId, 13, '0', STR_PAD_LEFT),
        $tenantId,
        $memberId + 10000,
        $memberId,
        'admin-web',
        new DateTimeImmutable('2031-01-01T00:00:00Z'),
        1,
    ), $requestId);
}

function createFileTenantSchema(PDO $pdo): void
{
    $pdo->exec(<<<'SQL'
CREATE TABLE pa_tenant (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  status VARCHAR(32) NOT NULL,
  PRIMARY KEY (id)
) ENGINE=InnoDB;
CREATE TABLE pa_file_cate (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  pid INT UNSIGNED NOT NULL DEFAULT 0,
  type TINYINT NOT NULL DEFAULT 10,
  name VARCHAR(64) NOT NULL DEFAULT '',
  create_time INT UNSIGNED NOT NULL DEFAULT 0,
  update_time INT UNSIGNED NOT NULL DEFAULT 0,
  delete_time INT UNSIGNED NULL DEFAULT NULL,
  tenant_id BIGINT UNSIGNED NOT NULL,
  PRIMARY KEY (id), KEY idx_type (type), UNIQUE KEY uk_file_cate_tenant_id (tenant_id, id),
  KEY idx_file_cate_tenant_type_parent (tenant_id, type, pid, id),
  CONSTRAINT fk_file_cate_tenant FOREIGN KEY (tenant_id) REFERENCES pa_tenant (id) ON DELETE RESTRICT
) ENGINE=InnoDB;
CREATE TABLE pa_file (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  cid INT UNSIGNED NOT NULL DEFAULT 0,
  source_id INT UNSIGNED NOT NULL DEFAULT 0,
  source TINYINT NOT NULL DEFAULT 0,
  type TINYINT NOT NULL DEFAULT 10,
  name VARCHAR(255) NOT NULL DEFAULT '',
  uri VARCHAR(255) NOT NULL DEFAULT '',
  storage VARCHAR(20) NOT NULL DEFAULT 'local',
  create_time INT UNSIGNED NOT NULL DEFAULT 0,
  update_time INT UNSIGNED NOT NULL DEFAULT 0,
  delete_time INT UNSIGNED NULL DEFAULT NULL,
  tenant_id BIGINT UNSIGNED NOT NULL,
  PRIMARY KEY (id), KEY idx_cid (cid), KEY idx_type (type),
  KEY idx_type_cid_source (type, cid, source), UNIQUE KEY uk_file_tenant_id (tenant_id, id),
  KEY idx_file_tenant_type_cid_source (tenant_id, type, cid, source, id),
  CONSTRAINT fk_file_tenant FOREIGN KEY (tenant_id) REFERENCES pa_tenant (id) ON DELETE RESTRICT
) ENGINE=InnoDB;
SQL);
}

$serverRoot = dirname(__DIR__, 2);
$host = getenv('DB_HOST') ?: '127.0.0.1';
$port = (int)(getenv('DB_PORT') ?: 3306);
$password = getenv('MYSQL_ROOT_PASSWORD') ?: 'mt02_root';
$runId = strtolower(bin2hex(random_bytes(5)));
$database = 'peanut_admin_mt03_file_' . $runId;
$admin = new PDO(
    "mysql:host={$host};port={$port};charset=utf8mb4",
    'root',
    $password,
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::MYSQL_ATTR_MULTI_STATEMENTS => true]
);
$admin->exec("CREATE DATABASE `{$database}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");

$storageRoot = $serverRoot . '/public/storage/tenants/v1';
$alphaDirectory = $storageRoot . '/101/uploads/images';
$betaDirectory = $storageRoot . '/202/uploads/images';
$objectName = 'same-object-' . $runId . '.png';
$alphaObject = $alphaDirectory . '/' . $objectName;
$betaObject = $betaDirectory . '/' . $objectName;

try {
    $pdo = new PDO(
        "mysql:host={$host};port={$port};dbname={$database};charset=utf8mb4",
        'root',
        $password,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES => false, PDO::MYSQL_ATTR_MULTI_STATEMENTS => true]
    );
    createFileTenantSchema($pdo);
    $pdo->exec("INSERT INTO pa_tenant (id, status) VALUES (101, 'active'), (202, 'active')");
    $pdo->exec("INSERT INTO pa_file_cate (id, tenant_id, pid, type, name) VALUES (11, 101, 0, 10, 'Alpha seed')");
    $pdo->exec("INSERT INTO pa_file (id, tenant_id, cid, source_id, source, type, name, uri, storage) VALUES (21, 101, 11, 1, 0, 10, 'alpha-seed.png', 'storage/tenants/v1/101/uploads/images/alpha-seed.png', 'local')");
    putenv('PHP_DB_HOST=' . $host);
    putenv('PHP_DB_PORT=' . $port);
    putenv('PHP_DB_NAME=' . $database);
    putenv('PHP_DB_USER=root');
    putenv('PHP_DB_PASS=' . $password);
    putenv('PHP_DB_PREFIX=pa_');
    $app = new think\App();
    $app->initialize();

    $alpha = fileTenantContext(101, 501, 'mt03-file-alpha-' . $runId);
    $beta = fileTenantContext(202, 502, 'mt03-file-beta-' . $runId);
    try {
        FileTenantContext::member(new stdClass());
        throw new RuntimeException('missing TenantContext unexpectedly succeeded');
    } catch (Throwable $exception) {
        expectFileTenant($exception->getMessage() !== '', 'missing context denial lost its shape');
    }

    expectFileTenant(
        FileObjectNamespace::directory($alpha, FileEnum::IMAGE) !== FileObjectNamespace::directory($beta, FileEnum::IMAGE),
        'two Tenants share the same object namespace'
    );
    expectFileTenant(FileCateLogic::add($alpha, ['tenant_id' => 202, 'pid' => 0, 'type' => 10, 'name' => 'Same category']), FileCateLogic::getError());
    expectFileTenant(FileCateLogic::add($beta, ['tenant_id' => 101, 'pid' => 0, 'type' => 10, 'name' => 'Same category']), FileCateLogic::getError());
    $alphaCategory = (int)FileTenantRepository::categories($alpha)->where('name', 'Same category')->value('id');
    $betaCategory = (int)FileTenantRepository::categories($beta)->where('name', 'Same category')->value('id');
    expectFileTenant($alphaCategory > 0 && $betaCategory > 0, 'same-name Tenant categories were not created');
    expectFileTenant(
        (int)$pdo->query("SELECT tenant_id FROM pa_file_cate WHERE id = {$alphaCategory}")->fetchColumn() === 101,
        'request payload forged category Tenant ownership'
    );

    mkdir($alphaDirectory, 0777, true);
    mkdir($betaDirectory, 0777, true);
    file_put_contents($alphaObject, 'alpha');
    file_put_contents($betaObject, 'beta');
    FileTenantRepository::createFile($alpha, [
        'tenant_id' => 202, 'cid' => $alphaCategory, 'source_id' => 501, 'source' => 0,
        'type' => 10, 'name' => 'same.png', 'uri' => 'storage/tenants/v1/101/uploads/images/' . $objectName, 'storage' => 'local',
    ]);
    FileTenantRepository::createFile($beta, [
        'tenant_id' => 101, 'cid' => $betaCategory, 'source_id' => 502, 'source' => 0,
        'type' => 10, 'name' => 'same.png', 'uri' => 'storage/tenants/v1/202/uploads/images/' . $objectName, 'storage' => 'local',
    ]);
    $alphaFile = (int)FileTenantRepository::files($alpha)->where('name', 'same.png')->value('id');
    $betaFile = (int)FileTenantRepository::files($beta)->where('name', 'same.png')->value('id');
    expectFileTenant($alphaFile > 0 && $betaFile > 0, 'same-name Tenant files were not created');

    expectFileTenant(count(FileLogic::lists($alpha, ['type' => 10, 'name' => 'same.png'])['lists']) === 1, 'Alpha file list leaked or lost same-name files');
    expectFileTenant(count(FileLogic::lists($beta, ['type' => 10, 'name' => 'same.png'])['lists']) === 1, 'Beta file list leaked or lost same-name files');
    expectFileTenant(count(FileCateLogic::lists($alpha, 10)) === 2, 'Alpha category tree leaked or lost categories');
    expectFileTenant(count(FileCateLogic::lists($beta, 10)) === 1, 'Beta category tree leaked or lost categories');

    foreach ([$betaCategory, 999999] as $target) {
        try {
            FileCateLogic::delete($alpha, $target);
            throw new RuntimeException('cross/missing category delete unexpectedly succeeded');
        } catch (InvalidArgumentException $exception) {
            expectFileTenant($exception->getMessage() === '分类不存在', 'category denial enumerated Tenant ownership');
        }
    }
    foreach ([$betaFile, 999999] as $target) {
        try {
            FileLogic::delete($alpha, [$target]);
            throw new RuntimeException('cross/missing file delete unexpectedly succeeded');
        } catch (InvalidArgumentException $exception) {
            expectFileTenant($exception->getMessage() === '包含不存在的素材', 'file denial enumerated Tenant ownership');
        }
    }
    try {
        FileTenantRepository::createFile($alpha, [
            'tenant_id' => 101, 'cid' => 0, 'source_id' => 501, 'source' => 0, 'type' => 10,
            'name' => 'forged.png', 'uri' => 'storage/tenants/v1/202/uploads/images/forged.png', 'storage' => 'local',
        ]);
        throw new RuntimeException('foreign object namespace unexpectedly succeeded');
    } catch (RuntimeException $exception) {
        expectFileTenant($exception->getMessage() === '素材对象不属于当前租户', 'object namespace denial changed');
    }

    $result = FileCateLogic::delete($alpha, $alphaCategory);
    expectFileTenant($result === ['categories_deleted' => 1, 'files_deleted' => 1, 'storage_deleted' => 1], 'Alpha category cleanup result changed');
    expectFileTenant(!file_exists($alphaObject), 'Alpha object survived Tenant cleanup');
    expectFileTenant(file_exists($betaObject) && file_get_contents($betaObject) === 'beta', 'Alpha cleanup touched Beta object');
    expectFileTenant(FileTenantRepository::findCategory($beta, $betaCategory) !== null, 'Alpha cleanup deleted Beta category');
    expectFileTenant(FileTenantRepository::findFile($beta, $betaFile) !== null, 'Alpha cleanup deleted Beta file row');
    expectFileTenant((int)$pdo->query("SELECT COUNT(*) FROM pa_file WHERE tenant_id = 202 AND delete_time IS NULL")->fetchColumn() === 1, 'Beta active file count changed');

    echo "MT03-FILE-TENANT-OWNERSHIP-001 passed\n";
} finally {
    if (file_exists($alphaObject)) {
        unlink($alphaObject);
    }
    if (file_exists($betaObject)) {
        unlink($betaObject);
    }
    foreach ([$alphaDirectory, $betaDirectory] as $directory) {
        @rmdir($directory);
        @rmdir(dirname($directory));
        @rmdir(dirname($directory, 2));
    }
    $admin->exec("DROP DATABASE IF EXISTS `{$database}`");
}
