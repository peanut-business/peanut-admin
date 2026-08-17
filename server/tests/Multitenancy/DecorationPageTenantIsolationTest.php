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
use PeanutAdmin\Kernel\Persistence\Schema\KernelSchema;

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

function decorationTenantDatabase(PDO $admin): string
{
    $database = 'peanut_admin_fresh_decorate_' . strtolower(bin2hex(random_bytes(5)));
    $admin->exec("CREATE DATABASE `{$database}` CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci");
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

function decorationFreshSchema(PDO $pdo, string $serverRoot): void
{
    foreach (KernelSchema::tableNames() as $table) {
        $pdo->exec(KernelSchema::createSql($table));
    }
    $pdo->exec(KernelSchema::addTenantMemberDepartmentForeignKeySql());
    $pdo->exec(<<<'SQL'
INSERT INTO pa_tenant
  (id, code, name, display_name, status, activated_at, created_at, updated_at)
VALUES
  (101, 'default', 'Alpha', 'Alpha', 'active', UTC_TIMESTAMP(3), UTC_TIMESTAMP(3), UTC_TIMESTAMP(3));
SQL);
    $schema = (string)file_get_contents($serverRoot . '/database/init.sql');
    expectDecorationTenant($schema !== '', 'canonical application schema is missing');
    $pdo->exec($schema);
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
        'styles' => [
            'position' => 'absolute',
            'left' => '40px',
            'top' => '75px',
            'width' => '750px',
            'height' => '340px',
        ],
    ]];
}

$serverRoot = dirname(__DIR__, 2);
$host = getenv('DB_HOST') ?: '127.0.0.1';
$port = (int)(getenv('DB_PORT') ?: 3306);
$password = getenv('MYSQL_ROOT_PASSWORD') ?: 'mt02_root';
$admin = new PDO(
    "mysql:host={$host};port={$port};charset=utf8mb4",
    'root',
    $password,
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);
$database = decorationTenantDatabase($admin);

try {
    $pdo = decorationTenantPdo($host, $port, $password, $database);
    decorationFreshSchema($pdo, $serverRoot);
    $pdo->exec(<<<'SQL'
INSERT INTO pa_tenant
  (id, code, name, display_name, status, activated_at, created_at, updated_at)
VALUES
  (202, 'beta', 'Beta', 'Beta', 'active', UTC_TIMESTAMP(3), UTC_TIMESTAMP(3), UTC_TIMESTAMP(3));
INSERT INTO pa_article_cate (id, tenant_id, name, is_show) VALUES
  (11, 101, 'Alpha Category', 1),
  (12, 202, 'Beta Category', 1);
INSERT INTO pa_article (id, tenant_id, cid, title, is_show) VALUES
  (21, 101, 11, 'Alpha Article', 1),
  (22, 202, 12, 'Beta Article', 1);
UPDATE pa_decorate_page SET name = 'Alpha PC' WHERE tenant_id = 101 AND type = 4;
SQL);
    $alphaPageId = (int)$pdo->query(
        'SELECT id FROM pa_decorate_page WHERE tenant_id = 101 AND type = 4'
    )->fetchColumn();
    $betaData = json_encode(decorationPcPage('Beta original', 22), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    $insertBeta = $pdo->prepare(
        'INSERT INTO pa_decorate_page (tenant_id, type, name, data, meta) VALUES (202, 4, ?, ?, ?)'
    );
    $insertBeta->execute(['Beta PC', $betaData, '[]']);
    $betaPageId = (int)$pdo->lastInsertId();

    expectDecorationTenant($alphaPageId > 0, 'fresh canonical Alpha PC page is missing');
    try {
        $pdo->prepare('INSERT INTO pa_decorate_page (tenant_id, type, name, data, meta) VALUES (202, 4, ?, ?, ?)')
            ->execute(['Duplicate Beta PC', $betaData, '[]']);
        throw new RuntimeException('same-Tenant decoration type duplicate unexpectedly succeeded');
    } catch (PDOException $exception) {
        expectDecorationTenant($exception->getCode() === '23000', 'decoration uniqueness failed unexpectedly');
    }
    try {
        $pdo->prepare('INSERT INTO pa_decorate_page (tenant_id, type, name, data, meta) VALUES (999, 5, ?, ?, ?)')
            ->execute(['Orphan page', $betaData, '[]']);
        throw new RuntimeException('orphan decoration page unexpectedly succeeded');
    } catch (PDOException $exception) {
        expectDecorationTenant($exception->getCode() === '23000', 'decoration Tenant foreign key failed unexpectedly');
    }

    putenv('PHP_DB_HOST=' . $host);
    putenv('PHP_DB_PORT=' . $port);
    putenv('PHP_DB_NAME=' . $database);
    putenv('PHP_DB_USER=root');
    putenv('PHP_DB_PASS=' . $password);
    putenv('PHP_DB_PREFIX=pa_');
    $app = new think\App($serverRoot);
    $app->initialize();

    $alpha = decorationTenantContext(101, 501, 'fresh-decoration-alpha');
    $beta = decorationTenantContext(202, 502, 'fresh-decoration-beta');
    try {
        DecorationTenantContext::member(new stdClass());
        throw new RuntimeException('missing TenantContext unexpectedly reached decoration Runtime');
    } catch (Throwable $exception) {
        expectDecorationTenant($exception->getMessage() !== '', 'missing context denial lost its shape');
    }

    expectDecorationTenant(count(DecorationPageLogic::lists($alpha, [DecorationEnum::PC_HOME])) === 1, 'Alpha page list crossed Tenant boundary');
    expectDecorationTenant(count(DecorationPageLogic::lists($beta, [DecorationEnum::PC_HOME])) === 1, 'Beta page list crossed Tenant boundary');
    expectDecorationTenant(DecorationPageLogic::detail($alpha, $betaPageId, [DecorationEnum::PC_HOME]) === false, 'cross-Tenant page detail was visible');
    $detailDenied = DecorationPageLogic::getError();
    expectDecorationTenant(DecorationPageLogic::detail($alpha, 999999, [DecorationEnum::PC_HOME]) === false, 'missing page detail unexpectedly succeeded');
    expectDecorationTenant(DecorationPageLogic::getError() === $detailDenied, 'page detail enumerated Tenant ownership');
    expectDecorationTenant(DecorationPageLogic::detailByType($alpha, DecorationEnum::PC_HOME)['name'] === 'Alpha PC', 'Alpha type read selected the wrong Tenant page');
    expectDecorationTenant(DecorationPageLogic::detailByType($beta, DecorationEnum::PC_HOME)['name'] === 'Beta PC', 'Beta type read selected the wrong Tenant page');

    $betaBefore = $pdo->query("SELECT name, data, meta FROM pa_decorate_page WHERE id = {$betaPageId}")->fetch(PDO::FETCH_ASSOC);
    expectDecorationTenant(!DecorationPageLogic::save($alpha, [
        'id' => $betaPageId,
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
        'id' => $alphaPageId,
        'tenant_id' => 202,
        'type' => DecorationEnum::PC_HOME,
        'data' => decorationPcPage('Forged Article', 22),
        'meta' => [],
    ], [DecorationEnum::PC_HOME]), 'cross-Tenant Article link unexpectedly passed decoration validation');
    $articleDenied = DecorationPageLogic::getError();
    expectDecorationTenant(!DecorationPageLogic::save($alpha, [
        'id' => $alphaPageId,
        'tenant_id' => 202,
        'type' => DecorationEnum::PC_HOME,
        'data' => decorationPcPage('Missing Article', 999999),
        'meta' => [],
    ], [DecorationEnum::PC_HOME]), 'missing Article link unexpectedly passed decoration validation');
    expectDecorationTenant(DecorationPageLogic::getError() === $articleDenied, 'Article link validation enumerated Tenant ownership');

    expectDecorationTenant(DecorationPageLogic::save($alpha, [
        'id' => $alphaPageId,
        'tenant_id' => 202,
        'type' => DecorationEnum::PC_HOME,
        'data' => decorationPcPage('Alpha saved', 21),
        'meta' => [],
    ], [DecorationEnum::PC_HOME]), DecorationPageLogic::getError());
    expectDecorationTenant(
        (int)$pdo->query("SELECT tenant_id FROM pa_decorate_page WHERE id = {$alphaPageId}")->fetchColumn() === 101,
        'payload tenant_id overrode trusted decoration owner'
    );
    expectDecorationTenant(
        $pdo->query("SELECT name, data, meta FROM pa_decorate_page WHERE id = {$betaPageId}")->fetch(PDO::FETCH_ASSOC) === $betaBefore,
        'cross-Tenant denials changed Beta decoration page'
    );

    $publicAlpha = new TenantSystemContext(
        101,
        DecorationTenantContext::PUBLIC_ACTOR,
        DecorationTenantContext::PC_PAGE_OPERATION,
        'fresh-decoration-public-alpha'
    );
    $publicBeta = new TenantSystemContext(
        202,
        DecorationTenantContext::PUBLIC_ACTOR,
        DecorationTenantContext::PC_PAGE_OPERATION,
        'fresh-decoration-public-beta'
    );
    expectDecorationTenant(
        DecorationReadService::pageByType($publicAlpha, DecorationEnum::PC_HOME, DecorationTenantContext::PC_PAGE_OPERATION)['name'] === 'Alpha PC',
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
            'fresh-decoration-forged'
        ), DecorationTenantContext::PC_PAGE_OPERATION)->count();
        throw new RuntimeException('untrusted public decoration context unexpectedly succeeded');
    } catch (Throwable $exception) {
        expectDecorationTenant($exception->getMessage() !== '', 'untrusted context denial lost its shape');
    }

    echo "MT02-DECORATION-PAGE-TENANT-ISOLATION-001 passed\n";
} finally {
    $admin->exec("DROP DATABASE IF EXISTS `{$database}`");
}
