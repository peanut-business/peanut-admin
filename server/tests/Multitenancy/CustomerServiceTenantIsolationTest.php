<?php
declare(strict_types=1);

use app\adminapi\logic\setting\CustomerServiceLogic;
use app\common\service\customer_service\CustomerServiceTenantContext;
use app\common\service\customer_service\CustomerServiceTenantRepository;
use PeanutAdmin\Kernel\Auth\TenantContext;
use PeanutAdmin\Kernel\Auth\ValidatedTenantSession;

require dirname(__DIR__, 2) . '/vendor/autoload.php';
spl_autoload_register(static function (string $class): void {
    if (!str_starts_with($class, 'app\\')) return;
    $path = dirname(__DIR__, 2) . '/app/' . str_replace('\\', '/', substr($class, 4)) . '.php';
    if (is_file($path)) require_once $path;
}, true, true);

function expectCustomerTenant(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}

function customerTenantContext(int $tenantId, int $memberId, string $requestId): TenantContext
{
    return TenantContext::fromValidatedSession(new ValidatedTenantSession(
        $memberId,
        '01JMT03CUSTOMER' . str_pad((string)$memberId, 12, '0', STR_PAD_LEFT),
        $tenantId,
        $memberId + 10000,
        $memberId,
        'admin-web',
        new DateTimeImmutable('2031-01-01T00:00:00Z'),
        1,
    ), $requestId);
}

function customerPdo(string $host, int $port, string $password, string $database): PDO
{
    return new PDO(
        "mysql:host={$host};port={$port};dbname={$database};charset=utf8mb4",
        'root',
        $password,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::MYSQL_ATTR_MULTI_STATEMENTS => true]
    );
}

function customerDatabase(PDO $admin): string
{
    $name = 'peanut_admin_mt03_customer_' . strtolower(bin2hex(random_bytes(5)));
    $admin->exec("CREATE DATABASE `{$name}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    return $name;
}

$serverRoot = dirname(__DIR__, 2);
$migration = (string)file_get_contents($serverRoot . '/database/migrations/20260813_customer_service_tenant_ownership.sql');
$fixture = (string)file_get_contents($serverRoot . '/tests/fixtures/mt03/customer-service-tenant-legacy.sql');
expectCustomerTenant($migration !== '' && $fixture !== '', 'customer-service migration or fixture is missing');

$host = getenv('DB_HOST') ?: '127.0.0.1';
$port = (int)(getenv('DB_PORT') ?: 3306);
$password = getenv('MYSQL_ROOT_PASSWORD') ?: 'peanut_admin_root_dev';
$admin = new PDO("mysql:host={$host};port={$port};charset=utf8mb4", 'root', $password, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$databases = [];

try {
    foreach (['missing_tenant', 'missing_file_owner', 'unknown_key', 'missing_qr', 'duplicate_qr'] as $failure) {
        $database = customerDatabase($admin);
        $databases[] = $database;
        $pdo = customerPdo($host, $port, $password, $database);
        $pdo->exec($fixture);
        if ($failure === 'missing_tenant') {
            $pdo->exec('DROP TABLE pa_tenant');
        } elseif ($failure === 'missing_file_owner') {
            $pdo->exec('ALTER TABLE pa_file MODIFY tenant_id BIGINT UNSIGNED NULL');
        } elseif ($failure === 'unknown_key') {
            $pdo->exec("INSERT INTO pa_config(type,name,value) VALUES('customer_service','future_unknown','x')");
        } elseif ($failure === 'missing_qr') {
            $pdo->exec('DELETE FROM pa_file WHERE id=11');
        } else {
            $pdo->exec("INSERT INTO pa_file(id,tenant_id,name,uri,storage) VALUES(33,202,'Duplicate','storage/uploads/images/alpha-qr.png','local')");
        }
        try {
            $pdo->exec($migration);
            throw new RuntimeException("{$failure} migration preflight unexpectedly succeeded");
        } catch (PDOException) {
            expectCustomerTenant(
                (int)$pdo->query("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pa_customer_service_setting'")->fetchColumn() === 0,
                "{$failure} created customer-service schema before refusing"
            );
        }
    }

    $database = customerDatabase($admin);
    $databases[] = $database;
    $pdo = customerPdo($host, $port, $password, $database);
    $pdo->exec($fixture);
    $pdo->exec($migration);
    expectCustomerTenant((int)$pdo->query('SELECT COUNT(*) FROM pa_customer_service_setting')->fetchColumn() === 2, 'not every existing Tenant received customer-service settings');
    expectCustomerTenant((int)$pdo->query('SELECT qr_file_id FROM pa_customer_service_setting WHERE tenant_id=101')->fetchColumn() === 11, 'legacy QR was not assigned to its owning Tenant');
    expectCustomerTenant($pdo->query('SELECT qr_file_id FROM pa_customer_service_setting WHERE tenant_id=202')->fetchColumn() === null, 'legacy QR leaked to non-owning Tenant');
    expectCustomerTenant((int)$pdo->query("SELECT COUNT(*) FROM pa_config WHERE type='customer_service'")->fetchColumn() === 0, 'legacy customer-service config remains a competing owner');
    expectCustomerTenant((string)$pdo->query("SELECT value FROM pa_config WHERE type='website' AND name='shop_name'")->fetchColumn() === 'Peanut Admin', 'unrelated instance config was modified');
    expectCustomerTenant($pdo->query("SELECT IS_NULLABLE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pa_customer_service_setting' AND COLUMN_NAME='tenant_id'")->fetchColumn() === 'NO', 'customer-service tenant_id is nullable');
    $indexes = $pdo->query("SELECT DISTINCT INDEX_NAME FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pa_customer_service_setting'")->fetchAll(PDO::FETCH_COLUMN);
    expectCustomerTenant(in_array('uk_customer_service_setting_tenant', $indexes, true), 'one-setting-per-Tenant constraint is missing');

    putenv('PHP_DB_HOST=' . $host); putenv('PHP_DB_PORT=' . $port); putenv('PHP_DB_NAME=' . $database);
    putenv('PHP_DB_USER=root'); putenv('PHP_DB_PASS=' . $password); putenv('PHP_DB_PREFIX=pa_');
    $app = new think\App(); $app->initialize();
    $request = request();
    $request->setHost('product.test');
    $alpha = customerTenantContext(101, 11, 'mt03-customer-alpha');
    $beta = customerTenantContext(202, 22, 'mt03-customer-beta');

    $alphaConfig = CustomerServiceLogic::getConfig($alpha);
    expectCustomerTenant(str_ends_with($alphaConfig['qr_code'], '/storage/uploads/images/alpha-qr.png'), 'Alpha QR URL lost storage provenance');
    CustomerServiceLogic::setConfig($beta, [
        'tenant_id' => 101,
        'qr_code' => 'http://product.test/storage/uploads/images/beta-qr.png',
        'wechat' => 'beta-support', 'phone' => '202', 'service_time' => 'always',
    ]);
    expectCustomerTenant(CustomerServiceLogic::getConfig($alpha)['wechat'] === 'peanut-support', 'Beta changed Alpha customer-service settings');
    expectCustomerTenant(CustomerServiceLogic::getConfig($beta)['wechat'] === 'beta-support', 'Beta customer-service settings were not updated');
    expectCustomerTenant((int)CustomerServiceTenantRepository::settings($alpha)->count() === 1, 'Alpha query crossed Tenant boundary');
    expectCustomerTenant((int)CustomerServiceTenantRepository::settings($beta)->count() === 1, 'Beta query crossed Tenant boundary');

    try {
        CustomerServiceLogic::setConfig($beta, ['qr_code' => 'http://product.test/storage/uploads/images/alpha-qr.png']);
        throw new RuntimeException('Beta referenced Alpha QR file');
    } catch (Throwable $exception) {
        expectCustomerTenant($exception->getMessage() !== '', 'cross-Tenant QR denial lost shape');
    }
    try {
        CustomerServiceTenantContext::tenantId([]);
        throw new RuntimeException('invalid Tenant context was accepted');
    } catch (Throwable $exception) {
        expectCustomerTenant($exception->getMessage() !== '', 'invalid Tenant context denial lost shape');
    }

    $controller = (string)file_get_contents($serverRoot . '/app/adminapi/controller/setting/CustomerServiceController.php');
    expectCustomerTenant(str_contains($controller, 'CustomerServiceTenantContext::member($this->request)'), 'customer-service controller lacks trusted TenantContext');
    expectCustomerTenant(str_contains($migration, 'public customer-service page remains owned by the Decoration Runtime'), 'Decoration consumer boundary is missing');
} finally {
    foreach ($databases as $database) $admin->exec("DROP DATABASE IF EXISTS `{$database}`");
}

echo "MT03-CUSTOMER-SERVICE-TENANT-001 passed\n";
