<?php
declare(strict_types=1);

use app\adminapi\logic\dict\DictDataLogic;
use app\adminapi\logic\dict\DictTypeLogic;
use app\common\service\dict\DictTenantContext;
use app\common\service\dict\DictTenantRepository;
use PeanutAdmin\Kernel\Auth\TenantContext;
use PeanutAdmin\Kernel\Auth\ValidatedTenantSession;

require dirname(__DIR__, 2) . '/vendor/autoload.php';

function expectDictTenant(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function dictTenantContext(int $tenantId, int $memberId, string $requestId): TenantContext
{
    return TenantContext::fromValidatedSession(new ValidatedTenantSession(
        $memberId,
        '01JMT02DICT' . str_pad((string)$memberId, 16, '0', STR_PAD_LEFT),
        $tenantId,
        $memberId + 10000,
        $memberId,
        'admin-web',
        new DateTimeImmutable('2031-01-01T00:00:00Z'),
        1,
    ), $requestId);
}

function dictTenantDatabase(PDO $admin, string $prefix): string
{
    $database = $prefix . strtolower(bin2hex(random_bytes(5)));
    $admin->exec("CREATE DATABASE `{$database}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    return $database;
}

function dictTenantPdo(string $host, int $port, string $password, string $database): PDO
{
    return new PDO(
        "mysql:host={$host};port={$port};dbname={$database};charset=utf8mb4",
        'root',
        $password,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::MYSQL_ATTR_MULTI_STATEMENTS => true,
        ]
    );
}

function createDictTenantSchema(PDO $pdo): void
{
    $pdo->exec(<<<'SQL'
CREATE TABLE pa_tenant (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  status VARCHAR(32) NOT NULL,
  PRIMARY KEY (id)
) ENGINE=InnoDB;
CREATE TABLE pa_dict_type (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name VARCHAR(100) NOT NULL DEFAULT '', type VARCHAR(100) NOT NULL DEFAULT '',
  is_disable TINYINT NOT NULL DEFAULT 0, remark VARCHAR(255) NOT NULL DEFAULT '',
  create_time INT UNSIGNED NOT NULL DEFAULT 0, update_time INT UNSIGNED NOT NULL DEFAULT 0,
  delete_time INT UNSIGNED NULL DEFAULT NULL, tenant_id BIGINT UNSIGNED NOT NULL,
  active_type VARCHAR(100) GENERATED ALWAYS AS (CASE WHEN delete_time IS NULL THEN type ELSE NULL END) STORED,
  PRIMARY KEY (id), KEY idx_type (type),
  UNIQUE KEY uk_dict_type_tenant_id (tenant_id, id),
  UNIQUE KEY uk_dict_type_tenant_active_type (tenant_id, active_type),
  KEY idx_dict_type_tenant_status_name (tenant_id, is_disable, name, id),
  CONSTRAINT fk_dict_type_tenant FOREIGN KEY (tenant_id) REFERENCES pa_tenant (id) ON DELETE RESTRICT
) ENGINE=InnoDB;
CREATE TABLE pa_dict_data (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name VARCHAR(100) NOT NULL DEFAULT '', value VARCHAR(255) NOT NULL DEFAULT '',
  type_id INT UNSIGNED NOT NULL DEFAULT 0, type_value VARCHAR(100) NOT NULL DEFAULT '',
  sort SMALLINT NOT NULL DEFAULT 0, is_disable TINYINT NOT NULL DEFAULT 0,
  remark VARCHAR(255) NOT NULL DEFAULT '', create_time INT UNSIGNED NOT NULL DEFAULT 0,
  update_time INT UNSIGNED NOT NULL DEFAULT 0, delete_time INT UNSIGNED NULL DEFAULT NULL,
  tenant_id BIGINT UNSIGNED NOT NULL,
  PRIMARY KEY (id), KEY idx_type_id (type_id),
  UNIQUE KEY uk_dict_data_tenant_id (tenant_id, id),
  KEY idx_dict_data_tenant_type_status_sort (tenant_id, type_id, is_disable, sort, id),
  CONSTRAINT fk_dict_data_tenant FOREIGN KEY (tenant_id) REFERENCES pa_tenant (id) ON DELETE RESTRICT,
  CONSTRAINT fk_dict_data_tenant_type FOREIGN KEY (tenant_id, type_id) REFERENCES pa_dict_type (tenant_id, id) ON DELETE RESTRICT
) ENGINE=InnoDB;
CREATE TABLE pa_system_dict_type (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  code VARCHAR(100) NOT NULL,
  name VARCHAR(100) NOT NULL DEFAULT '',
  is_disable TINYINT NOT NULL DEFAULT 0,
  remark VARCHAR(255) NOT NULL DEFAULT '',
  create_time INT UNSIGNED NOT NULL DEFAULT 0,
  update_time INT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (id), UNIQUE KEY uk_system_dict_type_code (code)
) ENGINE=InnoDB;
CREATE TABLE pa_system_dict_data (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  type_code VARCHAR(100) NOT NULL,
  name VARCHAR(100) NOT NULL DEFAULT '',
  value VARCHAR(255) NOT NULL DEFAULT '',
  sort SMALLINT NOT NULL DEFAULT 0,
  is_disable TINYINT NOT NULL DEFAULT 0,
  remark VARCHAR(255) NOT NULL DEFAULT '',
  create_time INT UNSIGNED NOT NULL DEFAULT 0,
  update_time INT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (id), UNIQUE KEY uk_system_dict_data_type_value (type_code, value),
  CONSTRAINT fk_system_dict_data_type FOREIGN KEY (type_code) REFERENCES pa_system_dict_type (code)
) ENGINE=InnoDB;
SQL);
}

function seedDictTenantSchema(PDO $pdo): void
{
    $pdo->exec(<<<'SQL'
INSERT INTO pa_tenant (id, status) VALUES (101, 'active'), (202, 'active');
INSERT INTO pa_dict_type (id, tenant_id, name, type) VALUES
  (11, 101, 'Alpha', 'shared_key'),
  (12, 202, 'Beta', 'shared_key');
INSERT INTO pa_dict_data (id, tenant_id, name, value, type_id, type_value, sort) VALUES
  (21, 101, 'Alpha item', 'alpha', 11, 'shared_key', 10),
  (22, 202, 'Beta item', 'beta', 12, 'shared_key', 20);
SQL);
}

$host = getenv('DB_HOST') ?: '127.0.0.1';
$port = (int)(getenv('DB_PORT') ?: 3306);
$password = getenv('MYSQL_ROOT_PASSWORD') ?: 'mt02_root';
$admin = new PDO(
    "mysql:host={$host};port={$port};charset=utf8mb4",
    'root',
    $password,
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);
$database = dictTenantDatabase($admin, 'peanut_admin_mt02_dict_');

try {
    $pdo = dictTenantPdo($host, $port, $password, $database);
    createDictTenantSchema($pdo);
    seedDictTenantSchema($pdo);
    try {
        $pdo->exec("INSERT INTO pa_dict_type (tenant_id, name, type) VALUES (202, 'Duplicate Beta', 'shared_key')");
        throw new RuntimeException('same-Tenant active dictionary type duplicate unexpectedly succeeded');
    } catch (PDOException $exception) {
        expectDictTenant($exception->getCode() === '23000', 'dictionary uniqueness failed with an unexpected shape');
    }

    putenv('PHP_DB_HOST=' . $host);
    putenv('PHP_DB_PORT=' . $port);
    putenv('PHP_DB_NAME=' . $database);
    putenv('PHP_DB_USER=root');
    putenv('PHP_DB_PASS=' . $password);
    putenv('PHP_DB_PREFIX=pa_');
    $app = new think\App();
    $app->initialize();

    $alpha = dictTenantContext(101, 501, 'mt02-dict-alpha');
    $beta = dictTenantContext(202, 502, 'mt02-dict-beta');
    try {
        DictTenantContext::member(new stdClass());
        throw new RuntimeException('missing TenantContext unexpectedly reached dictionary Runtime');
    } catch (Throwable $exception) {
        expectDictTenant($exception->getMessage() !== '', 'missing context denial lost its shape');
    }

    expectDictTenant(DictTypeLogic::lists($alpha, [])['count'] === 1, 'Alpha type list crossed Tenant boundary');
    expectDictTenant(DictTypeLogic::lists($beta, [])['count'] === 1, 'Beta type list crossed Tenant boundary');
    expectDictTenant(DictDataLogic::lists($alpha, [])['count'] === 1, 'Alpha data list crossed Tenant boundary');
    expectDictTenant(DictDataLogic::lists($beta, [])['count'] === 1, 'Beta data list crossed Tenant boundary');
    expectDictTenant(DictTypeLogic::detail($alpha, 12) === [], 'cross-Tenant type detail was visible');
    expectDictTenant(DictDataLogic::detail($alpha, 22) === [], 'cross-Tenant data detail was visible');
    expectDictTenant(DictTypeLogic::detail($alpha, 999999) === [], 'missing type detail shape changed');
    expectDictTenant(DictDataLogic::detail($alpha, 999999) === [], 'missing data detail shape changed');

    expectDictTenant(DictTypeLogic::add($alpha, [
        'tenant_id' => 202,
        'name' => 'Alpha status',
        'type' => 'status',
    ]), DictTypeLogic::getError());
    $alphaTypeId = (int)DictTenantRepository::types($alpha)->where('type', 'status')->value('id');
    expectDictTenant($alphaTypeId > 0, 'Alpha type was not created');
    expectDictTenant(
        (int)$pdo->query("SELECT tenant_id FROM pa_dict_type WHERE id = {$alphaTypeId}")->fetchColumn() === 101,
        'payload tenant_id overrode trusted dictionary owner'
    );
    expectDictTenant(!DictDataLogic::add($alpha, [
        'tenant_id' => 202,
        'type_id' => 12,
        'name' => 'Cross Tenant',
        'value' => 'forbidden',
    ]), 'cross-Tenant dictionary parent unexpectedly accepted a data item');

    expectDictTenant(DictDataLogic::add($alpha, [
        'tenant_id' => 202,
        'type_id' => $alphaTypeId,
        'name' => 'Enabled',
        'value' => '1',
        'sort' => 30,
    ]), DictDataLogic::getError());
    $alphaDataId = (int)DictTenantRepository::data($alpha)->where('type_id', $alphaTypeId)->value('id');
    expectDictTenant($alphaDataId > 0, 'Alpha dictionary data was not created');
    expectDictTenant(array_column(DictDataLogic::byType($alpha, 'status'), 'value') === ['1'], 'Alpha byType lost owned data');
    expectDictTenant(DictDataLogic::byType($beta, 'status') === [], 'Beta byType leaked Alpha data');

    $betaBefore = $pdo->query('SELECT name, type, is_disable FROM pa_dict_type WHERE id = 12')->fetch(PDO::FETCH_ASSOC);
    $betaDataBefore = $pdo->query('SELECT name, value, type_value, is_disable FROM pa_dict_data WHERE id = 22')->fetch(PDO::FETCH_ASSOC);
    expectDictTenant(!DictTypeLogic::edit($alpha, [
        'id' => 12, 'name' => 'Cross Tenant', 'type' => 'cross_tenant',
    ]), 'cross-Tenant type edit unexpectedly succeeded');
    $typeDenied = DictTypeLogic::getError();
    expectDictTenant(!DictTypeLogic::edit($alpha, [
        'id' => 999999, 'name' => 'Missing', 'type' => 'missing',
    ]), 'missing type edit unexpectedly succeeded');
    expectDictTenant(DictTypeLogic::getError() === $typeDenied, 'type edit enumerated Tenant ownership');

    expectDictTenant(!DictDataLogic::edit($alpha, [
        'id' => 22, 'name' => 'Cross Tenant', 'value' => 'forbidden',
    ]), 'cross-Tenant data edit unexpectedly succeeded');
    $dataDenied = DictDataLogic::getError();
    expectDictTenant(!DictDataLogic::edit($alpha, [
        'id' => 999999, 'name' => 'Missing', 'value' => 'missing',
    ]), 'missing data edit unexpectedly succeeded');
    expectDictTenant(DictDataLogic::getError() === $dataDenied, 'data edit enumerated Tenant ownership');

    expectDictTenant(DictTypeLogic::edit($alpha, [
        'id' => $alphaTypeId, 'name' => 'Alpha state', 'type' => 'state',
    ]), DictTypeLogic::getError());
    expectDictTenant(
        (string)$pdo->query("SELECT type_value FROM pa_dict_data WHERE id = {$alphaDataId}")->fetchColumn() === 'state',
        'Alpha type rename did not synchronize owned data'
    );
    expectDictTenant(
        (string)$pdo->query('SELECT type_value FROM pa_dict_data WHERE id = 22')->fetchColumn() === 'shared_key',
        'Alpha type rename changed Beta data'
    );
    expectDictTenant(!DictTypeLogic::delete($alpha, $alphaTypeId), 'occupied Alpha type deletion unexpectedly succeeded');
    expectDictTenant(DictTypeLogic::getError() === '字典类型已被数据项使用，请先删除数据项', 'occupied delete lost its failure shape');
    expectDictTenant(!DictTypeLogic::updateStatus($alpha, 12, 1), 'cross-Tenant type status unexpectedly changed');
    $typeStatusDenied = DictTypeLogic::getError();
    expectDictTenant(!DictTypeLogic::updateStatus($alpha, 999999, 1), 'missing type status unexpectedly changed');
    expectDictTenant(DictTypeLogic::getError() === $typeStatusDenied, 'type status enumerated Tenant ownership');
    expectDictTenant(!DictDataLogic::updateStatus($alpha, 22, 1), 'cross-Tenant data status unexpectedly changed');
    $dataStatusDenied = DictDataLogic::getError();
    expectDictTenant(!DictDataLogic::updateStatus($alpha, 999999, 1), 'missing data status unexpectedly changed');
    expectDictTenant(DictDataLogic::getError() === $dataStatusDenied, 'data status enumerated Tenant ownership');
    expectDictTenant(!DictTypeLogic::delete($alpha, 12), 'cross-Tenant type delete unexpectedly succeeded');
    expectDictTenant(!DictDataLogic::delete($alpha, 22), 'cross-Tenant data delete unexpectedly succeeded');

    expectDictTenant(
        $pdo->query('SELECT name, type, is_disable FROM pa_dict_type WHERE id = 12')->fetch(PDO::FETCH_ASSOC) === $betaBefore,
        'cross-Tenant denial changed Beta type'
    );
    expectDictTenant(
        $pdo->query('SELECT name, value, type_value, is_disable FROM pa_dict_data WHERE id = 22')->fetch(PDO::FETCH_ASSOC) === $betaDataBefore,
        'cross-Tenant denial changed Beta data'
    );
} finally {
    $admin->exec("DROP DATABASE IF EXISTS `{$database}`");
}

echo "MT02-DICT-TENANT-ISOLATION-001 passed\n";
