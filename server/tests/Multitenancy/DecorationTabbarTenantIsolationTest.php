<?php
declare(strict_types=1);

use app\adminapi\logic\decoration\DecorationTabbarLogic;
use app\common\service\decoration\DecorationReadService;
use app\common\service\decoration\DecorationTabbarTenantRepository;
use app\common\service\decoration\DecorationTenantContext;
use PeanutAdmin\Kernel\Auth\TenantContext;
use PeanutAdmin\Kernel\Auth\ValidatedTenantSession;
use PeanutAdmin\Kernel\Context\TenantSystemContext;

require dirname(__DIR__, 2) . '/vendor/autoload.php';

function expectTabbarTenant(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}

function tabbarTenantContext(int $tenantId, int $memberId, string $requestId): TenantContext
{
    return TenantContext::fromValidatedSession(new ValidatedTenantSession(
        $memberId,
        '01JMT03TABBAR' . str_pad((string)$memberId, 13, '0', STR_PAD_LEFT),
        $tenantId,
        $memberId + 10000,
        $memberId,
        'admin-web',
        new DateTimeImmutable('2031-01-01T00:00:00Z'),
        1,
    ), $requestId);
}

function tabbarDatabase(PDO $admin): string
{
    $database = 'peanut_admin_mt03_tabbar_' . strtolower(bin2hex(random_bytes(5)));
    $admin->exec("CREATE DATABASE `{$database}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    return $database;
}

function tabbarPdo(string $host, int $port, string $password, string $database): PDO
{
    return new PDO(
        "mysql:host={$host};port={$port};dbname={$database};charset=utf8mb4",
        'root',
        $password,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::MYSQL_ATTR_MULTI_STATEMENTS => true]
    );
}

/** @return list<array<string,mixed>> */
function tabbarItems(string $prefix): array
{
    return [
        [
            'name' => $prefix . ' Home', 'selected' => '', 'unselected' => '', 'is_show' => 1,
            'link' => ['target_type' => 'shop', 'target' => 'home'],
        ],
        [
            'name' => $prefix . ' News', 'selected' => '', 'unselected' => '', 'is_show' => 1,
            'link' => ['target_type' => 'shop', 'target' => 'news'],
        ],
    ];
}

$serverRoot = dirname(__DIR__, 2);
$migration = (string)file_get_contents(
    $serverRoot . '/database/migrations/20260813_decorate_tabbar_tenant_ownership.sql'
);
$fixture = (string)file_get_contents(
    $serverRoot . '/tests/fixtures/mt03/decoration-tabbar-tenant-legacy.sql'
);
expectTabbarTenant($migration !== '' && $fixture !== '', 'Tabbar Tenant migration or fixture is missing');

$host = getenv('DB_HOST') ?: '127.0.0.1';
$port = (int)(getenv('DB_PORT') ?: 3306);
$password = getenv('MYSQL_ROOT_PASSWORD') ?: 'peanut_admin_root_dev';
$admin = new PDO(
    "mysql:host={$host};port={$port};charset=utf8mb4",
    'root',
    $password,
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);
$databases = [];

try {
    foreach (['missing_bootstrap', 'incomplete_bootstrap', 'orphan_owner', 'invalid_style'] as $failure) {
        $database = tabbarDatabase($admin);
        $databases[] = $database;
        $pdo = tabbarPdo($host, $port, $password, $database);
        $pdo->exec($fixture);
        if ($failure === 'missing_bootstrap') {
            $pdo->exec('DROP TABLE pa_default_tenant_bootstrap');
        } elseif ($failure === 'incomplete_bootstrap') {
            $pdo->exec("UPDATE pa_default_tenant_bootstrap SET status='running'");
        } elseif ($failure === 'orphan_owner') {
            $pdo->exec('UPDATE pa_default_tenant_bootstrap SET tenant_id=999');
        } else {
            $pdo->exec("UPDATE pa_config SET value='[]' WHERE type='tabbar' AND name='style'");
        }
        try {
            $pdo->exec($migration);
            throw new RuntimeException("{$failure} migration preflight unexpectedly succeeded");
        } catch (PDOException) {
            expectTabbarTenant(
                (int)$pdo->query("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pa_decorate_tabbar' AND COLUMN_NAME='tenant_id'")->fetchColumn() === 0,
                "{$failure} changed Tabbar schema before preflight refusal"
            );
        }
    }

    $database = tabbarDatabase($admin);
    $databases[] = $database;
    $pdo = tabbarPdo($host, $port, $password, $database);
    $pdo->exec($fixture);
    $pdo->exec($migration);

    expectTabbarTenant(
        (int)$pdo->query('SELECT COUNT(*) FROM pa_decorate_tabbar WHERE tenant_id=101')->fetchColumn() === 2,
        'legacy Tabbar rows were not owned by the completed default Tenant'
    );
    expectTabbarTenant(
        $pdo->query("SELECT IS_NULLABLE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pa_decorate_tabbar' AND COLUMN_NAME='tenant_id'")->fetchColumn() === 'NO',
        'decorate_tabbar.tenant_id is nullable'
    );
    $indexes = $pdo->query(
        "SELECT DISTINCT INDEX_NAME FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pa_decorate_tabbar'"
    )->fetchAll(PDO::FETCH_COLUMN);
    foreach (['uk_decorate_tabbar_tenant_id', 'uk_decorate_tabbar_tenant_position'] as $index) {
        expectTabbarTenant(in_array($index, $indexes, true), "pa_decorate_tabbar.{$index} is missing");
    }
    expectTabbarTenant(
        (string)$pdo->query('SELECT style FROM pa_decorate_tabbar_setting WHERE tenant_id=101')->fetchColumn()
            === '{"default_color":"#111111","selected_color":"#2277EE"}',
        'legacy global Tabbar style was not moved to the default Tenant setting'
    );
    expectTabbarTenant(
        (int)$pdo->query("SELECT COUNT(*) FROM pa_config WHERE type='tabbar'")->fetchColumn() === 0,
        'legacy global Tabbar style remains a competing owner'
    );
    expectTabbarTenant(
        (string)$pdo->query("SELECT value FROM pa_config WHERE type='website' AND name='shop_name'")->fetchColumn() === 'Peanut Admin',
        'unrelated instance config was modified'
    );

    $pdo->exec(<<<'SQL'
INSERT INTO pa_decorate_tabbar_setting (tenant_id,style) VALUES
(202,'{"default_color":"#222222","selected_color":"#22AA44"}');
INSERT INTO pa_decorate_tabbar (id,tenant_id,position,name,selected,unselected,link,is_show) VALUES
(21,202,0,'Beta Home','','','{"target_type":"shop","target":"home"}',1),
(22,202,1,'Beta News','','','{"target_type":"shop","target":"news"}',1);
SQL);
    try {
        $pdo->exec("INSERT INTO pa_decorate_tabbar (tenant_id,position,name,link) VALUES (202,1,'Duplicate','{}')");
        throw new RuntimeException('same-Tenant position duplicate unexpectedly succeeded');
    } catch (PDOException $exception) {
        expectTabbarTenant($exception->getCode() === '23000', 'Tabbar position uniqueness failed unexpectedly');
    }

    putenv('PHP_DB_HOST=' . $host); putenv('PHP_DB_PORT=' . $port); putenv('PHP_DB_NAME=' . $database);
    putenv('PHP_DB_USER=root'); putenv('PHP_DB_PASS=' . $password); putenv('PHP_DB_PREFIX=pa_');
    $app = new think\App(); $app->initialize();
    $alpha = tabbarTenantContext(101, 11, 'mt03-tabbar-alpha');
    $beta = tabbarTenantContext(202, 22, 'mt03-tabbar-beta');

    expectTabbarTenant(DecorationTabbarLogic::detail($alpha)['list'][0]['name'] === 'Alpha Home', 'Alpha detail crossed Tenant boundary');
    expectTabbarTenant(DecorationTabbarLogic::detail($beta)['list'][0]['name'] === 'Beta Home', 'Beta detail crossed Tenant boundary');
    expectTabbarTenant(DecorationReadService::tabbar($alpha, true)['list'][0]['id'] === 11, 'same id/order read selected another Tenant');

    $betaBefore = $pdo->query('SELECT id,tenant_id,position,name,link,is_show FROM pa_decorate_tabbar WHERE tenant_id=202 ORDER BY position')->fetchAll(PDO::FETCH_ASSOC);
    expectTabbarTenant(DecorationTabbarLogic::save(
        $alpha,
        ['default_color' => '#333333', 'selected_color' => '#CC5500'],
        tabbarItems('Saved Alpha')
    ), DecorationTabbarLogic::getError());
    expectTabbarTenant(DecorationTabbarLogic::detail($alpha)['list'][0]['name'] === 'Saved Alpha Home', 'Alpha Tabbar save did not persist');
    expectTabbarTenant(
        $pdo->query('SELECT id,tenant_id,position,name,link,is_show FROM pa_decorate_tabbar WHERE tenant_id=202 ORDER BY position')->fetchAll(PDO::FETCH_ASSOC) === $betaBefore,
        'Alpha save changed Beta rows'
    );
    expectTabbarTenant(DecorationTabbarLogic::detail($beta)['style']['default_color'] === '#222222', 'Alpha save changed Beta style');

    $publicAlpha = new TenantSystemContext(
        101,
        DecorationTenantContext::PUBLIC_ACTOR,
        DecorationTenantContext::CONFIG_OPERATION,
        'mt03-tabbar-public-alpha'
    );
    expectTabbarTenant(
        DecorationReadService::tabbar($publicAlpha, true, DecorationTenantContext::CONFIG_OPERATION)['list'][0]['name'] === 'Saved Alpha Home',
        'trusted public Tabbar read selected another Tenant'
    );
    try {
        DecorationTabbarTenantRepository::items(new TenantSystemContext(
            101,
            'untrusted.actor',
            DecorationTenantContext::CONFIG_OPERATION,
            'mt03-tabbar-forged'
        ), DecorationTenantContext::CONFIG_OPERATION)->count();
        throw new RuntimeException('untrusted public Tabbar context unexpectedly succeeded');
    } catch (Throwable $exception) {
        expectTabbarTenant($exception->getMessage() !== '', 'untrusted context denial lost shape');
    }
    try {
        DecorationTenantContext::member(new stdClass());
        throw new RuntimeException('missing TenantContext unexpectedly reached Tabbar Runtime');
    } catch (Throwable $exception) {
        expectTabbarTenant($exception->getMessage() !== '', 'missing context denial lost shape');
    }

    echo "MT03-DECORATION-TABBAR-TENANT-001 passed\n";
} finally {
    foreach ($databases as $database) $admin->exec("DROP DATABASE IF EXISTS `{$database}`");
}
