<?php
declare(strict_types=1);

use app\adminapi\logic\setting\HotSearchLogic as AdminHotSearchLogic;
use app\api\logic\SearchLogic as ApiSearchLogic;
use app\common\service\hot_search\HotSearchTenantContext;
use PeanutAdmin\Kernel\Auth\TenantContext;
use PeanutAdmin\Kernel\Auth\ValidatedTenantSession;
use PeanutAdmin\Kernel\Context\TenantSystemContext;

require dirname(__DIR__, 2) . '/vendor/autoload.php';

function expectHotSearchTenant(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function hotSearchTenantContext(int $tenantId, int $memberId, string $requestId): TenantContext
{
    return TenantContext::fromValidatedSession(new ValidatedTenantSession(
        $memberId,
        '01JMT03HOTSEARCH' . str_pad((string)$memberId, 11, '0', STR_PAD_LEFT),
        $tenantId,
        $memberId + 10000,
        $memberId,
        'admin-web',
        new DateTimeImmutable('2031-01-01T00:00:00Z'),
        1,
    ), $requestId);
}

function createHotSearchTenantSchema(PDO $pdo, bool $withTenant = true): void
{
    if ($withTenant) {
        $pdo->exec(<<<'SQL'
CREATE TABLE pa_tenant (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  status VARCHAR(32) NOT NULL,
  PRIMARY KEY (id)
) ENGINE=InnoDB;
SQL);
    }
    $pdo->exec(<<<'SQL'
CREATE TABLE pa_hot_search (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name VARCHAR(200) NOT NULL DEFAULT '',
  sort SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  create_time INT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (id)
) ENGINE=InnoDB;
CREATE TABLE pa_config (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  type VARCHAR(30) NOT NULL DEFAULT '',
  name VARCHAR(60) NOT NULL DEFAULT '',
  value TEXT,
  create_time INT UNSIGNED NOT NULL DEFAULT 0,
  update_time INT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (id), UNIQUE KEY uk_type_name (type, name)
) ENGINE=InnoDB;
SQL);
}

$serverRoot = dirname(__DIR__, 2);
$host = getenv('DB_HOST') ?: '127.0.0.1';
$port = (int)(getenv('DB_PORT') ?: 3306);
$password = getenv('MYSQL_ROOT_PASSWORD') ?: 'mt02_root';
$runId = strtolower(bin2hex(random_bytes(5)));
$database = 'peanut_admin_mt03_hot_search_' . $runId;
$missingTenantDatabase = $database . '_missing';
$emptyTenantDatabase = $database . '_empty';
$ambiguousTenantDatabase = $database . '_ambiguous';
$admin = new PDO(
    "mysql:host={$host};port={$port};charset=utf8mb4",
    'root',
    $password,
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::MYSQL_ATTR_MULTI_STATEMENTS => true]
);
$migration = (string)file_get_contents($serverRoot . '/database/migrations/20260813_hot_search_tenant_ownership.sql');
expectHotSearchTenant($migration !== '', 'hot-search tenant migration is missing');

$databases = [$database, $missingTenantDatabase, $emptyTenantDatabase, $ambiguousTenantDatabase];
foreach ($databases as $name) {
    $admin->exec("CREATE DATABASE `{$name}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
}

try {
    foreach ([
        $missingTenantDatabase => 'missing',
        $emptyTenantDatabase => 'empty',
        $ambiguousTenantDatabase => 'ambiguous',
    ] as $name => $case) {
        $candidate = new PDO(
            "mysql:host={$host};port={$port};dbname={$name};charset=utf8mb4",
            'root',
            $password,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::MYSQL_ATTR_MULTI_STATEMENTS => true]
        );
        createHotSearchTenantSchema($candidate, $case !== 'missing');
        if ($case === 'ambiguous') {
            $candidate->exec("INSERT INTO pa_tenant (id, status) VALUES (101, 'active'), (202, 'active')");
        }
        try {
            $candidate->exec($migration);
            throw new RuntimeException("migration accepted {$case} active Tenant state");
        } catch (PDOException) {
            expectHotSearchTenant(
                (int)$candidate->query("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pa_hot_search' AND COLUMN_NAME = 'tenant_id'")->fetchColumn() === 0,
                "{$case} Tenant preflight mutated hot-search schema"
            );
        }
    }

    $pdo = new PDO(
        "mysql:host={$host};port={$port};dbname={$database};charset=utf8mb4",
        'root',
        $password,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES => false, PDO::MYSQL_ATTR_MULTI_STATEMENTS => true]
    );
    createHotSearchTenantSchema($pdo);
    $pdo->exec("INSERT INTO pa_tenant (id, status) VALUES (101, 'active')");
    $pdo->exec("INSERT INTO pa_hot_search (id, name, sort) VALUES (11, 'Legacy', 10)");
    $pdo->exec("INSERT INTO pa_config (type, name, value) VALUES ('hot_search', 'status', '1')");
    $pdo->exec($migration);
    expectHotSearchTenant((int)$pdo->query('SELECT tenant_id FROM pa_hot_search WHERE id = 11')->fetchColumn() === 101, 'legacy term was not backfilled');
    expectHotSearchTenant($pdo->query("SELECT IS_NULLABLE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pa_hot_search' AND COLUMN_NAME = 'tenant_id'")->fetchColumn() === 'NO', 'hot_search.tenant_id is nullable');
    $indexes = $pdo->query("SELECT DISTINCT INDEX_NAME FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pa_hot_search'")->fetchAll(PDO::FETCH_COLUMN);
    expectHotSearchTenant(in_array('uk_hot_search_tenant_id', $indexes, true), 'hot-search Tenant identity index is missing');
    expectHotSearchTenant(in_array('idx_hot_search_tenant_sort', $indexes, true), 'hot-search Tenant sort index is missing');

    $pdo->exec("INSERT INTO pa_tenant (id, status) VALUES (202, 'active')");
    $pdo->exec("INSERT INTO pa_hot_search (tenant_id, name, sort) VALUES (202, 'Same term', 30), (202, 'Beta only', 20)");
    putenv('PHP_DB_HOST=' . $host);
    putenv('PHP_DB_PORT=' . $port);
    putenv('PHP_DB_NAME=' . $database);
    putenv('PHP_DB_USER=root');
    putenv('PHP_DB_PASS=' . $password);
    putenv('PHP_DB_PREFIX=pa_');
    $app = new think\App();
    $app->initialize();

    $alpha = hotSearchTenantContext(101, 501, 'mt03-hot-search-alpha-' . $runId);
    $beta = hotSearchTenantContext(202, 502, 'mt03-hot-search-beta-' . $runId);
    $before = $pdo->query("SELECT id, name, sort FROM pa_hot_search WHERE tenant_id = 202 ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
    $statusBefore = (string)$pdo->query("SELECT value FROM pa_config WHERE type = 'hot_search' AND name = 'status'")->fetchColumn();

    try {
        HotSearchTenantContext::member(new stdClass());
        throw new RuntimeException('missing TenantContext unexpectedly reached hot-search write path');
    } catch (Throwable $exception) {
        expectHotSearchTenant($exception->getMessage() !== '', 'missing context denial lost its shape');
    }
    expectHotSearchTenant(
        $pdo->query("SELECT id, name, sort FROM pa_hot_search WHERE tenant_id = 202 ORDER BY id")->fetchAll(PDO::FETCH_ASSOC) === $before,
        'missing context changed Beta terms'
    );
    expectHotSearchTenant(
        (string)$pdo->query("SELECT value FROM pa_config WHERE type = 'hot_search' AND name = 'status'")->fetchColumn() === $statusBefore,
        'missing context changed instance status'
    );
    try {
        ApiSearchLogic::hotLists();
        throw new RuntimeException('missing public TenantContext unexpectedly read hot-search terms');
    } catch (Throwable $exception) {
        expectHotSearchTenant($exception->getMessage() !== '', 'missing public context denial lost its shape');
    }

    expectHotSearchTenant(AdminHotSearchLogic::setConfig($alpha, [
        'status' => 0,
        'data' => [
            ['tenant_id' => 202, 'name' => 'Same term', 'sort' => 90],
            ['tenant_id' => 202, 'name' => 'Alpha only', 'sort' => 80],
        ],
    ]), AdminHotSearchLogic::getError());
    expectHotSearchTenant(
        $pdo->query("SELECT id, name, sort FROM pa_hot_search WHERE tenant_id = 202 ORDER BY id")->fetchAll(PDO::FETCH_ASSOC) === $before,
        'Alpha full replacement changed Beta terms'
    );
    expectHotSearchTenant((int)$pdo->query("SELECT COUNT(*) FROM pa_hot_search WHERE tenant_id = 101")->fetchColumn() === 2, 'Alpha replacement did not remain Tenant-scoped');
    expectHotSearchTenant((int)$pdo->query("SELECT COUNT(*) FROM pa_hot_search WHERE tenant_id = 202 AND name = 'Same term'")->fetchColumn() === 1, 'Beta same-name term was deleted');
    expectHotSearchTenant((int)$pdo->query("SELECT COUNT(*) FROM pa_hot_search WHERE tenant_id = 202 AND name = 'Alpha only'")->fetchColumn() === 0, 'payload forged hot-search owner');
    expectHotSearchTenant((string)$pdo->query("SELECT value FROM pa_config WHERE type = 'hot_search' AND name = 'status'")->fetchColumn() === '0', 'instance-level status was not updated');

    $adminAlpha = AdminHotSearchLogic::getConfig($alpha);
    $adminBeta = AdminHotSearchLogic::getConfig($beta);
    expectHotSearchTenant(array_column($adminAlpha['data'], 'name') === ['Same term', 'Alpha only'], 'admin Alpha list leaked or lost terms');
    expectHotSearchTenant(array_column($adminBeta['data'], 'name') === ['Same term', 'Beta only'], 'admin Beta list leaked or lost terms');
    expectHotSearchTenant($adminAlpha['status'] === 0 && $adminBeta['status'] === 0, 'status was incorrectly represented as Tenant-owned');

    $publicAlpha = new TenantSystemContext(101, HotSearchTenantContext::PUBLIC_ACTOR, HotSearchTenantContext::PUBLIC_LIST_OPERATION, 'public-alpha-' . $runId);
    $publicBeta = new TenantSystemContext(202, HotSearchTenantContext::PUBLIC_ACTOR, HotSearchTenantContext::PUBLIC_LIST_OPERATION, 'public-beta-' . $runId);
    expectHotSearchTenant(array_column(ApiSearchLogic::hotLists($publicAlpha)['data'], 'name') === ['Same term', 'Alpha only'], 'public Alpha read crossed Tenant');
    expectHotSearchTenant(array_column(ApiSearchLogic::hotLists($publicBeta)['data'], 'name') === ['Same term', 'Beta only'], 'public Beta read crossed Tenant');
    try {
        ApiSearchLogic::hotLists(new TenantSystemContext(101, 'untrusted.actor', HotSearchTenantContext::PUBLIC_LIST_OPERATION, 'forged-' . $runId));
        throw new RuntimeException('untrusted public context unexpectedly read hot-search terms');
    } catch (Throwable $exception) {
        expectHotSearchTenant($exception->getMessage() !== '', 'untrusted public denial lost its shape');
    }
} finally {
    foreach ($databases as $name) {
        $admin->exec("DROP DATABASE IF EXISTS `{$name}`");
    }
}

echo "MT03-HOT-SEARCH-TENANT-ISOLATION-001 passed\n";
