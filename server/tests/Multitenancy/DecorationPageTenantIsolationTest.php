<?php
declare(strict_types=1);

use app\adminapi\logic\decoration\DecorationPageLogic;
use app\common\enum\decoration\DecorationEnum;
use app\common\service\decoration\DecorationReadService;
use app\common\service\decoration\DecorationTenantContext;
use app\common\service\decoration\DecorationTenantRepository;
use PeanutAdmin\Kernel\Auth\TenantContext;
use PeanutAdmin\Kernel\Auth\ValidatedTenantSession;
use PeanutAdmin\Kernel\Context\TenantSystemContext;

require dirname(__DIR__, 2) . '/vendor/autoload.php';

function expectDecorationTenant(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function decorationTenantContext(int $tenantId, int $memberId, string $requestId): TenantContext
{
    return TenantContext::fromValidatedSession(new ValidatedTenantSession(
        $memberId,
        '01JMT02DECORATE' . str_pad((string)$memberId, 12, '0', STR_PAD_LEFT),
        $tenantId,
        $memberId + 10000,
        $memberId,
        'admin-web',
        new DateTimeImmutable('2031-01-01T00:00:00Z'),
        1,
    ), $requestId);
}

function decorationTenantDatabase(PDO $admin, string $prefix): string
{
    $database = $prefix . strtolower(bin2hex(random_bytes(5)));
    $admin->exec("CREATE DATABASE `{$database}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    return $database;
}

function decorationTenantPdo(string $host, int $port, string $password, string $database): PDO
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

function createDecorationTenantSchema(PDO $pdo, string $failure = ''): void
{
    $pageUnique = $failure === 'duplicate_type' ? '' : ', UNIQUE KEY uk_decorate_page_type (`type`)';
    $pdo->exec(<<<SQL
CREATE TABLE pa_tenant (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  status VARCHAR(32) NOT NULL,
  PRIMARY KEY (id)
) ENGINE=InnoDB;
CREATE TABLE pa_decorate_page (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `type` TINYINT UNSIGNED NOT NULL,
  name VARCHAR(100) NOT NULL DEFAULT '',
  data LONGTEXT NOT NULL,
  meta LONGTEXT NOT NULL,
  create_time INT UNSIGNED NOT NULL DEFAULT 0,
  update_time INT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (id){$pageUnique}
) ENGINE=InnoDB;
SQL);

    if ($failure !== 'missing_bootstrap') {
        $pdo->exec(<<<'SQL'
CREATE TABLE pa_default_tenant_bootstrap (
  id TINYINT UNSIGNED NOT NULL,
  tenant_id BIGINT UNSIGNED NOT NULL,
  status VARCHAR(16) NOT NULL,
  PRIMARY KEY (id)
) ENGINE=InnoDB;
SQL);
    }
    $pdo->exec("INSERT INTO pa_tenant (id, status) VALUES (101, 'active'), (202, 'active')");
    if ($failure !== 'missing_bootstrap') {
        $status = $failure === 'incomplete_bootstrap' ? 'running' : 'completed';
        $tenantId = $failure === 'orphan_owner' ? 999 : 101;
        $pdo->exec("INSERT INTO pa_default_tenant_bootstrap (id, tenant_id, status) VALUES (1, {$tenantId}, '{$status}')");
    }
    $data = json_encode([
        [
            'title' => 'PC Banner',
            'name' => 'pc-banner',
            'content' => [
                'enabled' => 1,
                'data' => [[
                    'image' => '',
                    'name' => 'Legacy',
                    'link' => ['target_type' => 'shop', 'target' => 'home'],
                ]],
            ],
            'styles' => [],
        ],
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    $quotedData = $pdo->quote($data);
    $pdo->exec("INSERT INTO pa_decorate_page (id, type, name, data, meta) VALUES (11, 4, 'Legacy PC', {$quotedData}, '[]')");
    if ($failure === 'duplicate_type') {
        $pdo->exec("INSERT INTO pa_decorate_page (id, type, name, data, meta) VALUES (12, 4, 'Duplicate PC', {$quotedData}, '[]')");
    }
}

function decorationTenantColumnExists(PDO $pdo): bool
{
    return (int)$pdo->query(<<<'SQL'
SELECT COUNT(*) FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pa_decorate_page' AND COLUMN_NAME = 'tenant_id'
SQL)->fetchColumn() > 0;
}

/** @return array<int,array<string,mixed>> */
function decorationPcPage(string $name, int $articleId): array
{
    return [[
        'title' => 'PC Banner',
        'name' => 'pc-banner',
        'content' => [
            'enabled' => 1,
            'data' => [[
                'image' => '',
                'name' => $name,
                'link' => ['target_type' => 'article', 'target' => $articleId],
            ]],
        ],
        'styles' => [],
    ]];
}

$serverRoot = dirname(__DIR__, 2);
$migration = (string)file_get_contents(
    $serverRoot . '/database/migrations/20260813_decorate_page_tenant_ownership.sql'
);
expectDecorationTenant($migration !== '', 'decoration page Tenant migration is missing');

$host = getenv('DB_HOST') ?: '127.0.0.1';
$port = (int)(getenv('DB_PORT') ?: 3306);
$password = getenv('MYSQL_ROOT_PASSWORD') ?: 'mt02_root';
$admin = new PDO(
    "mysql:host={$host};port={$port};charset=utf8mb4",
    'root',
    $password,
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);
$databases = [];

try {
    foreach (['missing_bootstrap', 'incomplete_bootstrap', 'orphan_owner', 'duplicate_type'] as $failure) {
        $database = decorationTenantDatabase($admin, 'peanut_admin_mt02_decorate_preflight_');
        $databases[] = $database;
        $pdo = decorationTenantPdo($host, $port, $password, $database);
        createDecorationTenantSchema($pdo, $failure);
        try {
            $pdo->exec($migration);
            throw new RuntimeException("{$failure} migration preflight unexpectedly succeeded");
        } catch (PDOException) {
            expectDecorationTenant(
                !decorationTenantColumnExists($pdo),
                "{$failure} changed decorate_page schema before preflight rejection"
            );
        }
    }

    $database = decorationTenantDatabase($admin, 'peanut_admin_mt02_decorate_');
    $databases[] = $database;
    $pdo = decorationTenantPdo($host, $port, $password, $database);
    createDecorationTenantSchema($pdo);
    $pdo->exec($migration);

    expectDecorationTenant(
        (int)$pdo->query('SELECT tenant_id FROM pa_decorate_page WHERE id = 11')->fetchColumn() === 101,
        'legacy decoration page was not owned by completed bootstrap Tenant id=1'
    );
    expectDecorationTenant(
        $pdo->query("SELECT IS_NULLABLE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pa_decorate_page' AND COLUMN_NAME = 'tenant_id'")->fetchColumn() === 'NO',
        'decorate_page.tenant_id is nullable'
    );
    $indexes = $pdo->query(
        "SELECT DISTINCT INDEX_NAME FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pa_decorate_page'"
    )->fetchAll(PDO::FETCH_COLUMN);
    foreach (['uk_decorate_page_tenant_id', 'uk_decorate_page_tenant_type'] as $index) {
        expectDecorationTenant(in_array($index, $indexes, true), "pa_decorate_page.{$index} is missing");
    }
    $foreignKeys = $pdo->query(<<<'SQL'
SELECT CONSTRAINT_NAME FROM information_schema.REFERENTIAL_CONSTRAINTS
WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = 'pa_decorate_page'
SQL)->fetchAll(PDO::FETCH_COLUMN);
    expectDecorationTenant(
        in_array('fk_decorate_page_tenant', $foreignKeys, true),
        'decorate_page Tenant foreign key is missing'
    );

    $pdo->exec(<<<'SQL'
CREATE TABLE pa_article (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  tenant_id BIGINT UNSIGNED NOT NULL,
  title VARCHAR(255) NOT NULL DEFAULT '', image VARCHAR(2048) NULL,
  abstract TEXT NULL, is_show TINYINT UNSIGNED NOT NULL DEFAULT 1,
  delete_time INT UNSIGNED NULL DEFAULT NULL,
  PRIMARY KEY (id), UNIQUE KEY uk_article_tenant_id (tenant_id, id)
) ENGINE=InnoDB;
INSERT INTO pa_article (id, tenant_id, title, is_show) VALUES
  (21, 101, 'Alpha Article', 1),
  (22, 202, 'Beta Article', 1);
SQL);
    $betaData = json_encode(decorationPcPage('Beta original', 22), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    $pdo->prepare('INSERT INTO pa_decorate_page (id, tenant_id, type, name, data, meta) VALUES (12, 202, 4, ?, ?, ?)')
        ->execute(['Beta PC', $betaData, '[]']);
    try {
        $pdo->prepare('INSERT INTO pa_decorate_page (tenant_id, type, name, data, meta) VALUES (202, 4, ?, ?, ?)')
            ->execute(['Duplicate Beta PC', $betaData, '[]']);
        throw new RuntimeException('same-Tenant decoration type duplicate unexpectedly succeeded');
    } catch (PDOException $exception) {
        expectDecorationTenant($exception->getCode() === '23000', 'decoration uniqueness failed unexpectedly');
    }

    putenv('PHP_DB_HOST=' . $host);
    putenv('PHP_DB_PORT=' . $port);
    putenv('PHP_DB_NAME=' . $database);
    putenv('PHP_DB_USER=root');
    putenv('PHP_DB_PASS=' . $password);
    putenv('PHP_DB_PREFIX=pa_');
    $app = new think\App();
    $app->initialize();

    $alpha = decorationTenantContext(101, 501, 'mt02-decoration-alpha');
    $beta = decorationTenantContext(202, 502, 'mt02-decoration-beta');
    try {
        DecorationTenantContext::member(new stdClass());
        throw new RuntimeException('missing TenantContext unexpectedly reached decoration Runtime');
    } catch (Throwable $exception) {
        expectDecorationTenant($exception->getMessage() !== '', 'missing context denial lost its shape');
    }

    expectDecorationTenant(count(DecorationPageLogic::lists($alpha, [DecorationEnum::PC_HOME])) === 1, 'Alpha page list crossed Tenant boundary');
    expectDecorationTenant(count(DecorationPageLogic::lists($beta, [DecorationEnum::PC_HOME])) === 1, 'Beta page list crossed Tenant boundary');
    expectDecorationTenant(DecorationPageLogic::detail($alpha, 12, [DecorationEnum::PC_HOME]) === false, 'cross-Tenant page detail was visible');
    $detailDenied = DecorationPageLogic::getError();
    expectDecorationTenant(DecorationPageLogic::detail($alpha, 999999, [DecorationEnum::PC_HOME]) === false, 'missing page detail unexpectedly succeeded');
    expectDecorationTenant(DecorationPageLogic::getError() === $detailDenied, 'page detail enumerated Tenant ownership');
    expectDecorationTenant(DecorationPageLogic::detailByType($alpha, DecorationEnum::PC_HOME)['name'] === 'Legacy PC', 'Alpha type read selected the wrong Tenant page');
    expectDecorationTenant(DecorationPageLogic::detailByType($beta, DecorationEnum::PC_HOME)['name'] === 'Beta PC', 'Beta type read selected the wrong Tenant page');

    $betaBefore = $pdo->query('SELECT name, data, meta FROM pa_decorate_page WHERE id = 12')->fetch(PDO::FETCH_ASSOC);
    expectDecorationTenant(!DecorationPageLogic::save($alpha, [
        'id' => 12,
        'tenant_id' => 101,
        'type' => DecorationEnum::PC_HOME,
        'data' => decorationPcPage('Cross Tenant', 21),
        'meta' => [],
    ], [DecorationEnum::PC_HOME]), 'cross-Tenant decoration save unexpectedly succeeded');
    $saveDenied = DecorationPageLogic::getError();
    expectDecorationTenant(!DecorationPageLogic::save($alpha, [
        'id' => 999999,
        'tenant_id' => 101,
        'type' => DecorationEnum::PC_HOME,
        'data' => decorationPcPage('Missing', 21),
        'meta' => [],
    ], [DecorationEnum::PC_HOME]), 'missing decoration save unexpectedly succeeded');
    expectDecorationTenant(DecorationPageLogic::getError() === $saveDenied, 'decoration save enumerated Tenant ownership');

    expectDecorationTenant(!DecorationPageLogic::save($alpha, [
        'id' => 11,
        'tenant_id' => 202,
        'type' => DecorationEnum::PC_HOME,
        'data' => decorationPcPage('Forged Article', 22),
        'meta' => [],
    ], [DecorationEnum::PC_HOME]), 'cross-Tenant Article link unexpectedly passed decoration validation');
    $articleDenied = DecorationPageLogic::getError();
    expectDecorationTenant(!DecorationPageLogic::save($alpha, [
        'id' => 11,
        'tenant_id' => 202,
        'type' => DecorationEnum::PC_HOME,
        'data' => decorationPcPage('Missing Article', 999999),
        'meta' => [],
    ], [DecorationEnum::PC_HOME]), 'missing Article link unexpectedly passed decoration validation');
    expectDecorationTenant(DecorationPageLogic::getError() === $articleDenied, 'Article link validation enumerated Tenant ownership');

    expectDecorationTenant(DecorationPageLogic::save($alpha, [
        'id' => 11,
        'tenant_id' => 202,
        'type' => DecorationEnum::PC_HOME,
        'data' => decorationPcPage('Alpha saved', 21),
        'meta' => [],
    ], [DecorationEnum::PC_HOME]), DecorationPageLogic::getError());
    expectDecorationTenant(
        (int)$pdo->query('SELECT tenant_id FROM pa_decorate_page WHERE id = 11')->fetchColumn() === 101,
        'payload tenant_id overrode trusted decoration owner'
    );
    expectDecorationTenant(
        $pdo->query('SELECT name, data, meta FROM pa_decorate_page WHERE id = 12')->fetch(PDO::FETCH_ASSOC) === $betaBefore,
        'cross-Tenant denials changed Beta decoration page'
    );

    $publicAlpha = new TenantSystemContext(
        101,
        DecorationTenantContext::PUBLIC_ACTOR,
        DecorationTenantContext::PC_PAGE_OPERATION,
        'mt02-decoration-public-alpha'
    );
    $publicBeta = new TenantSystemContext(
        202,
        DecorationTenantContext::PUBLIC_ACTOR,
        DecorationTenantContext::PC_PAGE_OPERATION,
        'mt02-decoration-public-beta'
    );
    expectDecorationTenant(
        DecorationReadService::pageByType($publicAlpha, DecorationEnum::PC_HOME, DecorationTenantContext::PC_PAGE_OPERATION)['name'] === 'Legacy PC',
        'public Alpha read selected another Tenant page'
    );
    expectDecorationTenant(
        DecorationReadService::pageByType($publicBeta, DecorationEnum::PC_HOME, DecorationTenantContext::PC_PAGE_OPERATION)['name'] === 'Beta PC',
        'public Beta read selected another Tenant page'
    );
    try {
        DecorationTenantRepository::pages(new TenantSystemContext(
            101,
            'untrusted.actor',
            DecorationTenantContext::PC_PAGE_OPERATION,
            'mt02-decoration-forged'
        ), DecorationTenantContext::PC_PAGE_OPERATION)->count();
        throw new RuntimeException('untrusted public decoration context unexpectedly succeeded');
    } catch (Throwable $exception) {
        expectDecorationTenant($exception->getMessage() !== '', 'untrusted context denial lost its shape');
    }

    echo "MT02-DECORATION-PAGE-TENANT-ISOLATION-001 passed\n";
} finally {
    foreach ($databases as $database) {
        $admin->exec("DROP DATABASE IF EXISTS `{$database}`");
    }
}
