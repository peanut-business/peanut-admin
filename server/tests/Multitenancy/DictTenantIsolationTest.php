<?php
declare(strict_types=1);

use app\adminapi\application\dict\DictDataApplicationService;
use app\adminapi\application\dict\DictTypeApplicationService;
use app\common\execution\ExecutionContext;
use app\common\execution\ExecutionContextStore;
use app\common\service\dict\DictTenantContext;
use PeanutAdmin\Kernel\Auth\TenantContext;
use PeanutAdmin\Kernel\Auth\ValidatedTenantSession;

require dirname(__DIR__, 2) . '/vendor/autoload.php';
require __DIR__ . '/../Support/IsolatedBackendEnvironment.php';

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

function dictTenantDatabase(PDO $admin, string $database): string
{
    $admin->exec("CREATE DATABASE `{$database}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    return $database;
}

function dictTenantEnv(string $name): string
{
    $value = getenv($name);
    if ($value === false || $value === '') {
        throw new RuntimeException("missing required environment variable: {$name}");
    }
    return $value;
}

function dictTenantPdo(string $host, int $port, string $password, string $database): PDO
{
    return new PDO(
        "mysql:host={$host};port={$port};dbname={$database};charset=utf8mb4",
        dictTenantEnv('DB_USER'),
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

$host = dictTenantEnv('DB_HOST');
$port = (int)dictTenantEnv('DB_PORT');
$user = dictTenantEnv('DB_USER');
$password = dictTenantEnv('DB_PASS');
$database = dictTenantEnv('DB_NAME');
$admin = new PDO(
    "mysql:host={$host};port={$port};charset=utf8mb4",
    $user,
    $password,
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);
$database = dictTenantDatabase($admin, $database);

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

    IsolatedBackendEnvironment::activateDatabase($host, $port, $database, $user, $password, 'multi-tenant');
    $app = new think\App();
    $app->initialize();

    $alpha = dictTenantContext(101, 501, 'mt02-dict-alpha');
    $beta = dictTenantContext(202, 502, 'mt02-dict-beta');
    try {
        DictTenantContext::member();
        throw new RuntimeException('missing TenantContext unexpectedly reached dictionary Runtime');
    } catch (Throwable $exception) {
        expectDictTenant($exception->getMessage() !== '', 'missing context denial lost its shape');
    }

    expectDictTenant(
        app(ExecutionContextStore::class)->run(
            ExecutionContext::tenantAdmin($alpha, 'test.dict.type.list.alpha'),
            fn() => app(DictTypeApplicationService::class)->lists([]),
        )->total() === 1,
        'Alpha type list crossed Tenant boundary',
    );
    expectDictTenant(
        app(ExecutionContextStore::class)->run(
            ExecutionContext::tenantAdmin($beta, 'test.dict.type.list.beta'),
            fn() => app(DictTypeApplicationService::class)->lists([]),
        )->total() === 1,
        'Beta type list crossed Tenant boundary',
    );
    expectDictTenant(
        app(ExecutionContextStore::class)->run(
            ExecutionContext::tenantAdmin($alpha, 'test.dict.data.list.alpha'),
            fn() => app(DictDataApplicationService::class)->lists([]),
        )->total() === 1,
        'Alpha data list crossed Tenant boundary',
    );
    expectDictTenant(
        app(ExecutionContextStore::class)->run(
            ExecutionContext::tenantAdmin($beta, 'test.dict.data.list.beta'),
            fn() => app(DictDataApplicationService::class)->lists([]),
        )->total() === 1,
        'Beta data list crossed Tenant boundary',
    );
    expectDictTenant(
        app(ExecutionContextStore::class)->run(
            ExecutionContext::tenantAdmin($alpha, 'test.dict.type.detail.cross-tenant'),
            fn() => app(DictTypeApplicationService::class)->detail(12),
        ) === [],
        'cross-Tenant type detail was visible',
    );
    expectDictTenant(
        app(ExecutionContextStore::class)->run(
            ExecutionContext::tenantAdmin($alpha, 'test.dict.data.detail.cross-tenant'),
            fn() => app(DictDataApplicationService::class)->detail(22),
        ) === [],
        'cross-Tenant data detail was visible',
    );
    expectDictTenant(
        app(ExecutionContextStore::class)->run(
            ExecutionContext::tenantAdmin($alpha, 'test.dict.type.detail.missing'),
            fn() => app(DictTypeApplicationService::class)->detail(999999),
        ) === [],
        'missing type detail shape changed',
    );
    expectDictTenant(
        app(ExecutionContextStore::class)->run(
            ExecutionContext::tenantAdmin($alpha, 'test.dict.data.detail.missing'),
            fn() => app(DictDataApplicationService::class)->detail(999999),
        ) === [],
        'missing data detail shape changed',
    );

    expectDictTenant(
        app(ExecutionContextStore::class)->run(
            ExecutionContext::tenantAdmin($alpha, 'test.dict.type.add'),
            fn() => app(DictTypeApplicationService::class)->add([
                'tenant_id' => 202,
                'name' => 'Alpha status',
                'type' => 'status',
            ]),
        ),
        app(DictTypeApplicationService::class)->getError(),
    );
    $alphaTypeId = (int)$pdo->query("SELECT id FROM pa_dict_type WHERE tenant_id = 101 AND type = 'status' LIMIT 1")->fetchColumn();
    expectDictTenant($alphaTypeId > 0, 'Alpha type was not created');
    expectDictTenant(
        (int)$pdo->query("SELECT tenant_id FROM pa_dict_type WHERE id = {$alphaTypeId}")->fetchColumn() === 101,
        'payload tenant_id overrode trusted dictionary owner'
    );
    expectDictTenant(
        !app(ExecutionContextStore::class)->run(
            ExecutionContext::tenantAdmin($alpha, 'test.dict.data.add.cross-tenant'),
            fn() => app(DictDataApplicationService::class)->add([
                'tenant_id' => 202,
                'type_id' => 12,
                'name' => 'Cross Tenant',
                'value' => 'forbidden',
            ]),
        ),
        'cross-Tenant dictionary parent unexpectedly accepted a data item',
    );

    expectDictTenant(
        app(ExecutionContextStore::class)->run(
            ExecutionContext::tenantAdmin($alpha, 'test.dict.data.add'),
            fn() => app(DictDataApplicationService::class)->add([
                'tenant_id' => 202,
                'type_id' => $alphaTypeId,
                'name' => 'Enabled',
                'value' => '1',
                'sort' => 30,
            ]),
        ),
        app(DictDataApplicationService::class)->getError(),
    );
    $alphaDataId = (int)$pdo->query("SELECT id FROM pa_dict_data WHERE tenant_id = 101 AND type_id = {$alphaTypeId} LIMIT 1")->fetchColumn();
    expectDictTenant($alphaDataId > 0, 'Alpha dictionary data was not created');
    expectDictTenant(
        array_column(app(ExecutionContextStore::class)->run(
            ExecutionContext::tenantAdmin($alpha, 'test.dict.data.by-type.alpha'),
            fn() => app(DictDataApplicationService::class)->byType('status'),
        ), 'value') === ['1'],
        'Alpha byType lost owned data',
    );
    expectDictTenant(
        app(ExecutionContextStore::class)->run(
            ExecutionContext::tenantAdmin($beta, 'test.dict.data.by-type.beta'),
            fn() => app(DictDataApplicationService::class)->byType('status'),
        ) === [],
        'Beta byType leaked Alpha data',
    );

    $betaBefore = $pdo->query('SELECT name, type, is_disable FROM pa_dict_type WHERE id = 12')->fetch(PDO::FETCH_ASSOC);
    $betaDataBefore = $pdo->query('SELECT name, value, type_value, is_disable FROM pa_dict_data WHERE id = 22')->fetch(PDO::FETCH_ASSOC);
    expectDictTenant(
        !app(ExecutionContextStore::class)->run(
            ExecutionContext::tenantAdmin($alpha, 'test.dict.type.edit.cross-tenant'),
            fn() => app(DictTypeApplicationService::class)->edit([
                'id' => 12, 'name' => 'Cross Tenant', 'type' => 'cross_tenant',
            ]),
        ),
        'cross-Tenant type edit unexpectedly succeeded',
    );
    $typeDenied = app(DictTypeApplicationService::class)->getError();
    expectDictTenant(
        !app(ExecutionContextStore::class)->run(
            ExecutionContext::tenantAdmin($alpha, 'test.dict.type.edit.missing'),
            fn() => app(DictTypeApplicationService::class)->edit([
                'id' => 999999, 'name' => 'Missing', 'type' => 'missing',
            ]),
        ),
        'missing type edit unexpectedly succeeded',
    );
    expectDictTenant(app(DictTypeApplicationService::class)->getError() === $typeDenied, 'type edit enumerated Tenant ownership');

    expectDictTenant(
        !app(ExecutionContextStore::class)->run(
            ExecutionContext::tenantAdmin($alpha, 'test.dict.data.edit.cross-tenant'),
            fn() => app(DictDataApplicationService::class)->edit([
                'id' => 22, 'name' => 'Cross Tenant', 'value' => 'forbidden',
            ]),
        ),
        'cross-Tenant data edit unexpectedly succeeded',
    );
    $dataDenied = app(DictDataApplicationService::class)->getError();
    expectDictTenant(
        !app(ExecutionContextStore::class)->run(
            ExecutionContext::tenantAdmin($alpha, 'test.dict.data.edit.missing'),
            fn() => app(DictDataApplicationService::class)->edit([
                'id' => 999999, 'name' => 'Missing', 'value' => 'missing',
            ]),
        ),
        'missing data edit unexpectedly succeeded',
    );
    expectDictTenant(app(DictDataApplicationService::class)->getError() === $dataDenied, 'data edit enumerated Tenant ownership');

    expectDictTenant(
        app(ExecutionContextStore::class)->run(
            ExecutionContext::tenantAdmin($alpha, 'test.dict.type.edit'),
            fn() => app(DictTypeApplicationService::class)->edit([
                'id' => $alphaTypeId, 'name' => 'Alpha state', 'type' => 'state',
            ]),
        ),
        app(DictTypeApplicationService::class)->getError(),
    );
    expectDictTenant(
        (string)$pdo->query("SELECT type_value FROM pa_dict_data WHERE id = {$alphaDataId}")->fetchColumn() === 'state',
        'Alpha type rename did not synchronize owned data'
    );
    expectDictTenant(
        (string)$pdo->query('SELECT type_value FROM pa_dict_data WHERE id = 22')->fetchColumn() === 'shared_key',
        'Alpha type rename changed Beta data'
    );
    expectDictTenant(
        !app(ExecutionContextStore::class)->run(
            ExecutionContext::tenantAdmin($alpha, 'test.dict.type.delete.occupied'),
            fn() => app(DictTypeApplicationService::class)->delete($alphaTypeId),
        ),
        'occupied Alpha type deletion unexpectedly succeeded',
    );
    expectDictTenant(app(DictTypeApplicationService::class)->getError() === '字典类型已被数据项使用，请先删除数据项', 'occupied delete lost its failure shape');
    expectDictTenant(
        !app(ExecutionContextStore::class)->run(
            ExecutionContext::tenantAdmin($alpha, 'test.dict.type.status.cross-tenant'),
            fn() => app(DictTypeApplicationService::class)->updateStatus(12, 1),
        ),
        'cross-Tenant type status unexpectedly changed',
    );
    $typeStatusDenied = app(DictTypeApplicationService::class)->getError();
    expectDictTenant(
        !app(ExecutionContextStore::class)->run(
            ExecutionContext::tenantAdmin($alpha, 'test.dict.type.status.missing'),
            fn() => app(DictTypeApplicationService::class)->updateStatus(999999, 1),
        ),
        'missing type status unexpectedly changed',
    );
    expectDictTenant(app(DictTypeApplicationService::class)->getError() === $typeStatusDenied, 'type status enumerated Tenant ownership');
    expectDictTenant(
        !app(ExecutionContextStore::class)->run(
            ExecutionContext::tenantAdmin($alpha, 'test.dict.data.status.cross-tenant'),
            fn() => app(DictDataApplicationService::class)->updateStatus(22, 1),
        ),
        'cross-Tenant data status unexpectedly changed',
    );
    $dataStatusDenied = app(DictDataApplicationService::class)->getError();
    expectDictTenant(
        !app(ExecutionContextStore::class)->run(
            ExecutionContext::tenantAdmin($alpha, 'test.dict.data.status.missing'),
            fn() => app(DictDataApplicationService::class)->updateStatus(999999, 1),
        ),
        'missing data status unexpectedly changed',
    );
    expectDictTenant(app(DictDataApplicationService::class)->getError() === $dataStatusDenied, 'data status enumerated Tenant ownership');
    expectDictTenant(
        !app(ExecutionContextStore::class)->run(
            ExecutionContext::tenantAdmin($alpha, 'test.dict.type.delete.cross-tenant'),
            fn() => app(DictTypeApplicationService::class)->delete(12),
        ),
        'cross-Tenant type delete unexpectedly succeeded',
    );
    expectDictTenant(
        !app(ExecutionContextStore::class)->run(
            ExecutionContext::tenantAdmin($alpha, 'test.dict.data.delete.cross-tenant'),
            fn() => app(DictDataApplicationService::class)->delete(22),
        ),
        'cross-Tenant data delete unexpectedly succeeded',
    );

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
