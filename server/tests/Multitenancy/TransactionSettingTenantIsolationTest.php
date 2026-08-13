<?php
declare(strict_types=1);

use app\adminapi\logic\setting\TransactionSettingsLogic;
use app\common\service\transaction\TransactionSettingTenantContext;
use app\common\service\transaction\TransactionSettingTenantRepository;
use PeanutAdmin\Kernel\Auth\TenantContext;
use PeanutAdmin\Kernel\Auth\ValidatedTenantSession;

require dirname(__DIR__, 2) . '/vendor/autoload.php';
spl_autoload_register(static function (string $class): void {
    if (!str_starts_with($class, 'app\\')) return;
    $path = dirname(__DIR__, 2) . '/app/' . str_replace('\\', '/', substr($class, 4)) . '.php';
    if (is_file($path)) require_once $path;
}, true, true);

function expectTransactionTenant(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}

function transactionTenantContext(int $tenantId, int $memberId, string $requestId): TenantContext
{
    return TenantContext::fromValidatedSession(new ValidatedTenantSession(
        $memberId,
        '01JMT03TRANSACTION' . str_pad((string)$memberId, 10, '0', STR_PAD_LEFT),
        $tenantId,
        $memberId + 10000,
        $memberId,
        'admin-web',
        new DateTimeImmutable('2031-01-01T00:00:00Z'),
        1,
    ), $requestId);
}

function transactionPdo(string $host, int $port, string $password, string $database): PDO
{
    return new PDO(
        "mysql:host={$host};port={$port};dbname={$database};charset=utf8mb4",
        'root',
        $password,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::MYSQL_ATTR_MULTI_STATEMENTS => true]
    );
}

function transactionDatabase(PDO $admin): string
{
    $name = 'peanut_admin_mt03_transaction_' . strtolower(bin2hex(random_bytes(5)));
    $admin->exec("CREATE DATABASE `{$name}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    return $name;
}

$serverRoot = dirname(__DIR__, 2);
$migration = (string)file_get_contents($serverRoot . '/database/migrations/20260813_transaction_setting_tenant_ownership.sql');
$fixture = (string)file_get_contents($serverRoot . '/tests/fixtures/mt03/transaction-setting-legacy.sql');
expectTransactionTenant($migration !== '' && $fixture !== '', 'transaction setting migration or fixture is missing');

$host = getenv('DB_HOST') ?: '127.0.0.1';
$port = (int)(getenv('DB_PORT') ?: 3306);
$password = getenv('MYSQL_ROOT_PASSWORD') ?: 'peanut_admin_root_dev';
$admin = new PDO("mysql:host={$host};port={$port};charset=utf8mb4", 'root', $password, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$databases = [];

try {
    foreach (['missing_tenant', 'missing_config', 'invalid_mode', 'invalid_time', 'unknown_key'] as $failure) {
        $database = transactionDatabase($admin);
        $databases[] = $database;
        $pdo = transactionPdo($host, $port, $password, $database);
        $pdo->exec($fixture);
        if ($failure === 'missing_tenant') {
            $pdo->exec('DROP TABLE pa_tenant');
        } elseif ($failure === 'missing_config') {
            $pdo->exec('DROP TABLE pa_config');
        } elseif ($failure === 'invalid_mode') {
            $pdo->exec("UPDATE pa_config SET value='2' WHERE type='transaction' AND name='verification_orders'");
        } elseif ($failure === 'invalid_time') {
            $pdo->exec("UPDATE pa_config SET value='0' WHERE type='transaction' AND name='cancel_unpaid_orders_times'");
        } else {
            $pdo->exec("INSERT INTO pa_config(type,name,value) VALUES('transaction','future_unknown','1')");
        }
        try {
            $pdo->exec($migration);
            throw new RuntimeException("{$failure} migration preflight unexpectedly succeeded");
        } catch (PDOException) {
            expectTransactionTenant(
                (int)$pdo->query("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pa_transaction_setting'")->fetchColumn() === 0,
                "{$failure} created transaction setting schema before refusing"
            );
        }
    }

    $database = transactionDatabase($admin);
    $databases[] = $database;
    $pdo = transactionPdo($host, $port, $password, $database);
    $pdo->exec($fixture);
    $pdo->exec($migration);

    expectTransactionTenant((int)$pdo->query('SELECT COUNT(*) FROM pa_transaction_setting')->fetchColumn() === 2, 'not every existing Tenant received transaction policy');
    foreach ([101, 202] as $tenantId) {
        $row = $pdo->query("SELECT * FROM pa_transaction_setting WHERE tenant_id={$tenantId}")->fetch(PDO::FETCH_ASSOC);
        expectTransactionTenant((int)$row['cancel_unpaid_orders'] === 0, "Tenant {$tenantId} lost legacy cancel mode");
        expectTransactionTenant((int)$row['cancel_unpaid_orders_times'] === 45, "Tenant {$tenantId} lost legacy cancel threshold");
        expectTransactionTenant((int)$row['verification_orders_times'] === 36, "Tenant {$tenantId} lost legacy verification threshold");
    }
    expectTransactionTenant((int)$pdo->query("SELECT COUNT(*) FROM pa_config WHERE type='transaction'")->fetchColumn() === 0, 'legacy transaction config remains a competing owner');
    expectTransactionTenant((string)$pdo->query("SELECT value FROM pa_config WHERE type='website' AND name='shop_name'")->fetchColumn() === 'Peanut Admin', 'unrelated instance config was modified');
    expectTransactionTenant($pdo->query("SELECT IS_NULLABLE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pa_transaction_setting' AND COLUMN_NAME='tenant_id'")->fetchColumn() === 'NO', 'transaction setting tenant_id is nullable');
    $indexes = $pdo->query("SELECT DISTINCT INDEX_NAME FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pa_transaction_setting'")->fetchAll(PDO::FETCH_COLUMN);
    expectTransactionTenant(in_array('uk_transaction_setting_tenant', $indexes, true), 'one-policy-per-Tenant constraint is missing');

    putenv('PHP_DB_HOST=' . $host); putenv('PHP_DB_PORT=' . $port); putenv('PHP_DB_NAME=' . $database);
    putenv('PHP_DB_USER=root'); putenv('PHP_DB_PASS=' . $password); putenv('PHP_DB_PREFIX=pa_');
    $app = new think\App(); $app->initialize();
    $alpha = transactionTenantContext(101, 11, 'mt03-transaction-alpha');
    $beta = transactionTenantContext(202, 22, 'mt03-transaction-beta');

    TransactionSettingsLogic::setConfig($beta, [
        'tenant_id' => 101,
        'cancel_unpaid_orders' => 1,
        'cancel_unpaid_orders_times' => 60,
        'verification_orders' => 0,
        'verification_orders_times' => 48,
    ]);
    expectTransactionTenant(TransactionSettingsLogic::getConfig($alpha)['cancel_unpaid_orders'] === 0, 'Beta changed Alpha transaction policy');
    expectTransactionTenant(TransactionSettingsLogic::getConfig($beta)['cancel_unpaid_orders_times'] === 60, 'Beta transaction policy was not updated');
    expectTransactionTenant((int)TransactionSettingTenantRepository::settings($alpha)->count() === 1, 'Alpha query crossed Tenant boundary');
    expectTransactionTenant((int)TransactionSettingTenantRepository::settings($beta)->count() === 1, 'Beta query crossed Tenant boundary');

    $pdo->exec("INSERT INTO pa_tenant(id,code,status) VALUES(303,'gamma','active')");
    $gamma = transactionTenantContext(303, 33, 'mt03-transaction-gamma');
    expectTransactionTenant(TransactionSettingsLogic::getConfig($gamma) === [
        'cancel_unpaid_orders' => 1,
        'cancel_unpaid_orders_times' => 30,
        'verification_orders' => 1,
        'verification_orders_times' => 24,
    ], 'new Tenant did not receive stable Runtime defaults');
    TransactionSettingsLogic::setConfig($gamma, [
        'tenant_id' => 101,
        'cancel_unpaid_orders' => 0,
        'verification_orders' => 0,
    ]);
    expectTransactionTenant((int)$pdo->query('SELECT tenant_id FROM pa_transaction_setting WHERE tenant_id=303')->fetchColumn() === 303, 'payload forged new policy Tenant ownership');

    try {
        TransactionSettingTenantContext::tenantId([]);
        throw new RuntimeException('invalid Tenant context was accepted');
    } catch (Throwable $exception) {
        expectTransactionTenant($exception->getMessage() !== '', 'invalid Tenant context denial lost shape');
    }

    expectTransactionTenant(str_contains($migration, 'Other pa_config types remain instance-owned'), 'instance config boundary is missing');
} finally {
    foreach ($databases as $database) $admin->exec("DROP DATABASE IF EXISTS `{$database}`");
}

echo "MT03-TRANSACTION-SETTING-TENANT-001 passed\n";
