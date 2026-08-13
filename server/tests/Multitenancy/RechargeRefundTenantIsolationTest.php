<?php
declare(strict_types=1);

use app\api\logic\RechargeLogic as ApiRechargeLogic;
use app\common\model\finance\RechargeOrder;
use app\common\service\finance\FinanceTenantContext;
use app\common\service\finance\FinanceTenantRepository;
use app\common\service\finance\VerifiedPaymentTenantResolver;
use app\common\service\member\MemberTenantRepository;
use PeanutAdmin\Kernel\Auth\TenantContext;
use PeanutAdmin\Kernel\Auth\ValidatedTenantSession;

require dirname(__DIR__, 2) . '/vendor/autoload.php';
spl_autoload_register(static function (string $class): void {
    if (!str_starts_with($class, 'app\\')) return;
    $path = dirname(__DIR__, 2) . '/app/' . str_replace('\\', '/', substr($class, 4)) . '.php';
    if (is_file($path)) require_once $path;
}, true, true);

function expectFinanceTenant(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}

function financeTenantContext(int $tenantId, int $memberId, string $requestId): TenantContext
{
    return TenantContext::fromValidatedSession(new ValidatedTenantSession(
        $memberId,
        '01JMT03FINANCE' . str_pad((string)$memberId, 13, '0', STR_PAD_LEFT),
        $tenantId,
        $memberId + 10000,
        $memberId,
        'admin-web',
        new DateTimeImmutable('2031-01-01T00:00:00Z'),
        1,
    ), $requestId);
}

function financePdo(string $host, int $port, string $password, string $database): PDO
{
    return new PDO(
        "mysql:host={$host};port={$port};dbname={$database};charset=utf8mb4",
        'root',
        $password,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::MYSQL_ATTR_MULTI_STATEMENTS => true]
    );
}

function financeDatabase(PDO $admin, string $prefix): string
{
    $name = $prefix . strtolower(bin2hex(random_bytes(5)));
    $admin->exec("CREATE DATABASE `{$name}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    return $name;
}

$serverRoot = dirname(__DIR__, 2);
$migration = (string)file_get_contents($serverRoot . '/database/migrations/20260813_recharge_refund_tenant_ownership.sql');
$fixture = (string)file_get_contents($serverRoot . '/tests/fixtures/mt03/recharge-refund-tenant-legacy.sql');
expectFinanceTenant($migration !== '' && $fixture !== '', 'finance migration or fixture is missing');

$host = getenv('DB_HOST') ?: '127.0.0.1';
$port = (int)(getenv('DB_PORT') ?: 3306);
$password = getenv('MYSQL_ROOT_PASSWORD') ?: 'mt02_root';
$admin = new PDO("mysql:host={$host};port={$port};charset=utf8mb4", 'root', $password, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$databases = [];

try {
    foreach (['missing_table', 'default_tenant_unavailable', 'invalid_relation'] as $failure) {
        $database = financeDatabase($admin, 'peanut_admin_mt03_finance_preflight_');
        $databases[] = $database;
        $pdo = financePdo($host, $port, $password, $database);
        $pdo->exec($fixture);
        if ($failure === 'missing_table') {
            $pdo->exec('DROP TABLE pa_default_tenant_bootstrap');
        } elseif ($failure === 'default_tenant_unavailable') {
            $pdo->exec("UPDATE pa_default_tenant_bootstrap SET status='running'");
        } else {
            $pdo->exec('UPDATE pa_refund_log SET user_id = 999 WHERE id = 41');
        }
        try {
            $pdo->exec($migration);
            throw new RuntimeException("{$failure} migration preflight unexpectedly succeeded");
        } catch (PDOException) {
            foreach (['pa_recharge_order', 'pa_refund_record'] as $table) {
                $count = (int)$pdo->query("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='{$table}' AND COLUMN_NAME='tenant_id'")->fetchColumn();
                expectFinanceTenant($count === 0, "{$failure} changed {$table} before refusing");
            }
        }
    }

    $database = financeDatabase($admin, 'peanut_admin_mt03_finance_');
    $databases[] = $database;
    $pdo = financePdo($host, $port, $password, $database);
    $pdo->exec($fixture);
    $pdo->exec($migration);
    foreach (['pa_recharge_order', 'pa_refund_record', 'pa_refund_log'] as $table) {
        expectFinanceTenant((int)$pdo->query("SELECT tenant_id FROM `{$table}` LIMIT 1")->fetchColumn() === 101, "{$table} was not backfilled");
        expectFinanceTenant($pdo->query("SELECT IS_NULLABLE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='{$table}' AND COLUMN_NAME='tenant_id'")->fetchColumn() === 'NO', "{$table}.tenant_id is nullable");
    }
    foreach ([
        'pa_recharge_order' => ['uk_recharge_order_tenant_id', 'idx_recharge_order_tenant_member_time'],
        'pa_refund_record' => ['uk_refund_record_tenant_order', 'idx_refund_record_tenant_status_time'],
        'pa_refund_log' => ['uk_refund_log_tenant_id', 'idx_refund_log_tenant_record_time'],
    ] as $table => $required) {
        $actual = $pdo->query("SELECT DISTINCT INDEX_NAME FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='{$table}'")->fetchAll(PDO::FETCH_COLUMN);
        foreach ($required as $index) expectFinanceTenant(in_array($index, $actual, true), "{$table}.{$index} is missing");
    }
    $rechargeIndexes = $pdo->query("SELECT DISTINCT INDEX_NAME FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pa_recharge_order'")->fetchAll(PDO::FETCH_COLUMN);
    foreach (['uk_sn', 'uk_pay_sn', 'uk_transaction_id'] as $globalIndex) {
        expectFinanceTenant(in_array($globalIndex, $rechargeIndexes, true), "global merchant identity {$globalIndex} was lowered");
    }
    $refundIndexes = $pdo->query("SELECT DISTINCT INDEX_NAME FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pa_refund_record'")->fetchAll(PDO::FETCH_COLUMN);
    expectFinanceTenant(in_array('uk_refund_record_order_global', $refundIndexes, true), 'global one-refund-per-order invariant was lowered');

    $pdo->exec("INSERT INTO pa_tenant (id,code,status) VALUES (202,'beta','active')");
    $pdo->exec("INSERT INTO pa_member (id,tenant_id,sn,account,nickname,user_money,balance,total_recharge_amount) VALUES (22,202,'M-BETA','beta','Beta',20,20,0)");
    putenv('PHP_DB_HOST=' . $host); putenv('PHP_DB_PORT=' . $port); putenv('PHP_DB_NAME=' . $database);
    putenv('PHP_DB_USER=root'); putenv('PHP_DB_PASS=' . $password); putenv('PHP_DB_PREFIX=pa_');
    $app = new think\App(); $app->initialize();
    $alpha = financeTenantContext(101, 501, 'mt03-finance-alpha');
    $beta = financeTenantContext(202, 502, 'mt03-finance-beta');

    $betaOrder = FinanceTenantRepository::createOrder($beta, [
        'tenant_id' => 101,
        'sn' => 'RC-BETA-22', 'user_id' => 22, 'pay_sn' => null, 'pay_way' => 2,
        'pay_status' => RechargeOrder::PAY_STATUS_UNPAID, 'order_amount' => '5.00',
        'order_terminal' => 3, 'transaction_id' => null, 'refund_status' => 0,
    ]);
    expectFinanceTenant((int)$betaOrder->tenant_id === 202, 'payload forged order Tenant ownership');
    expectFinanceTenant(FinanceTenantRepository::orders($alpha)->where('id', (int)$betaOrder->id)->findOrEmpty()->isEmpty(), 'Alpha read Beta order');

    $system = VerifiedPaymentTenantResolver::resolve('RC-BETA-22');
    expectFinanceTenant(FinanceTenantContext::tenantId($system) === 202, 'verified callback did not resolve Beta Tenant');
    $betaBefore = (string)MemberTenantRepository::members($beta)->where('id', 22)->value('user_money');
    expectFinanceTenant(ApiRechargeLogic::settle([
        'order_sn' => 'RC-BETA-22', 'pay_way' => 2, 'transaction_id' => 'TX-BETA-22',
        'amount_cents' => 500, 'currency' => 'CNY', 'status' => 'success',
    ]), 'verified callback settlement failed: ' . ApiRechargeLogic::getError());
    expectFinanceTenant((string)MemberTenantRepository::members($beta)->where('id', 22)->value('user_money') !== $betaBefore, 'verified callback did not credit Beta');
    expectFinanceTenant((int)MemberTenantRepository::balanceLogs($beta)->where('source_sn', 'RC-BETA-22')->count() === 1, 'verified callback did not append Beta ledger');
    expectFinanceTenant((int)MemberTenantRepository::balanceLogs($alpha)->where('source_sn', 'RC-BETA-22')->count() === 0, 'verified callback leaked ledger to Alpha');
    try {
        FinanceTenantContext::tenantId(new PeanutAdmin\Kernel\Context\TenantSystemContext(202, 'forged', 'finance.recharge.settle', 'x'));
        throw new RuntimeException('forged payment system actor was accepted');
    } catch (Throwable $e) {
        expectFinanceTenant($e->getMessage() !== '', 'forged actor denial lost shape');
    }

    $migrationSource = $migration;
    expectFinanceTenant(str_contains($migrationSource, 'pa_payment_scene and pa_config(type=pay/recharge) remain instance-owned'), 'instance payment configuration boundary is missing');
} finally {
    foreach ($databases as $database) $admin->exec("DROP DATABASE IF EXISTS `{$database}`");
}

echo "MT03-RECHARGE-REFUND-TENANT-001 passed\n";
