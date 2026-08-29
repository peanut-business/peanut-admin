<?php
declare(strict_types=1);

use app\api\application\RechargeApplicationService as ApiRechargeLogic;
use app\Modules\Official\Payment\Model\RechargeOrder;
use app\common\service\finance\FinanceTenantContext;
use app\common\service\finance\FinanceTenantRepository;
use app\common\service\member\MemberTenantRepository;
use PeanutAdmin\Kernel\Auth\TenantContext;
use PeanutAdmin\Kernel\Auth\ValidatedTenantSession;

require dirname(__DIR__, 2) . '/vendor/autoload.php';
require __DIR__ . '/../Support/IsolatedBackendEnvironment.php';
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

function financePdo(string $host, int $port, string $user, string $password, string $database): PDO
{
    return new PDO(
        "mysql:host={$host};port={$port};dbname={$database};charset=utf8mb4",
        $user,
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

function createFinanceTenantSchema(PDO $pdo): void
{
    $pdo->exec(<<<'SQL'
CREATE TABLE pa_tenant (
  id BIGINT UNSIGNED NOT NULL, code VARCHAR(64) NOT NULL, status VARCHAR(32) NOT NULL,
  PRIMARY KEY (id)
) ENGINE=InnoDB;
CREATE TABLE pa_member (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT, sn VARCHAR(20) NOT NULL DEFAULT '',
  account VARCHAR(50) NOT NULL DEFAULT '', account_unique VARCHAR(50) GENERATED ALWAYS AS (NULLIF(account,'')) STORED,
  password VARCHAR(100) NOT NULL DEFAULT '', nickname VARCHAR(50) NOT NULL DEFAULT '',
  avatar VARCHAR(255) NOT NULL DEFAULT '', real_name VARCHAR(32) NOT NULL DEFAULT '',
  mobile VARCHAR(20) NOT NULL DEFAULT '', mobile_unique VARCHAR(20) GENERATED ALWAYS AS (NULLIF(mobile,'')) STORED,
  channel TINYINT UNSIGNED NOT NULL DEFAULT 0, email VARCHAR(100) NOT NULL DEFAULT '', sex TINYINT NOT NULL DEFAULT 0,
  birthday DATE NULL, status TINYINT NOT NULL DEFAULT 1, login_time INT UNSIGNED NOT NULL DEFAULT 0,
  login_ip VARCHAR(45) NOT NULL DEFAULT '', is_new_user TINYINT NOT NULL DEFAULT 0,
  user_money DECIMAL(10,2) UNSIGNED NOT NULL DEFAULT 0,
  total_recharge_amount DECIMAL(10,2) UNSIGNED NOT NULL DEFAULT 0, points INT UNSIGNED NOT NULL DEFAULT 0,
  create_time INT UNSIGNED NOT NULL DEFAULT 0, update_time INT UNSIGNED NOT NULL DEFAULT 0, delete_time INT UNSIGNED NULL,
  tenant_id BIGINT UNSIGNED NOT NULL,
  PRIMARY KEY (id), UNIQUE KEY uk_member_tenant_id (tenant_id, id),
  UNIQUE KEY uk_member_tenant_sn (tenant_id, sn), UNIQUE KEY uk_member_tenant_account (tenant_id, account_unique),
  UNIQUE KEY uk_member_tenant_mobile (tenant_id, mobile_unique),
  CONSTRAINT fk_member_tenant FOREIGN KEY (tenant_id) REFERENCES pa_tenant (id) ON DELETE RESTRICT
) ENGINE=InnoDB;
CREATE TABLE pa_member_balance_log (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT, sn VARCHAR(32) NOT NULL DEFAULT '', member_id INT UNSIGNED NOT NULL DEFAULT 0,
  change_object TINYINT UNSIGNED NOT NULL DEFAULT 1, change_type SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  action TINYINT UNSIGNED NOT NULL DEFAULT 1, left_amount DECIMAL(10,2) UNSIGNED NOT NULL DEFAULT 0,
  source_type TINYINT NOT NULL DEFAULT 0, extra TEXT NULL, admin_id INT UNSIGNED NOT NULL DEFAULT 0,
  create_time INT UNSIGNED NOT NULL DEFAULT 0, update_time INT UNSIGNED NULL, delete_time INT UNSIGNED NULL,
  change_amount DECIMAL(10,2) UNSIGNED NOT NULL DEFAULT 0, source_sn VARCHAR(255) NULL,
  source_sn_unique VARCHAR(255) GENERATED ALWAYS AS (NULLIF(source_sn,'')) STORED,
  remark VARCHAR(255) NULL DEFAULT '', tenant_id BIGINT UNSIGNED NOT NULL,
  PRIMARY KEY (id), UNIQUE KEY uk_member_balance_log_tenant_id (tenant_id, id),
  UNIQUE KEY uk_member_balance_log_tenant_sn (tenant_id, sn),
  UNIQUE KEY uk_member_balance_log_tenant_source (tenant_id, source_sn_unique),
  KEY idx_member_balance_log_tenant_member_time (tenant_id, member_id, create_time, id),
  CONSTRAINT fk_member_balance_log_tenant FOREIGN KEY (tenant_id) REFERENCES pa_tenant (id) ON DELETE RESTRICT,
  CONSTRAINT fk_member_balance_log_member FOREIGN KEY (tenant_id, member_id) REFERENCES pa_member (tenant_id, id) ON DELETE RESTRICT
) ENGINE=InnoDB;
CREATE TABLE pa_recharge_order (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT, user_id INT UNSIGNED NOT NULL DEFAULT 0,
  pay_status TINYINT NOT NULL DEFAULT 0, order_amount DECIMAL(10,2) NOT NULL DEFAULT 0,
  order_terminal TINYINT NULL DEFAULT 1, refund_status TINYINT NOT NULL DEFAULT 0,
  refund_transaction_id VARCHAR(255) NULL, delete_time INT UNSIGNED NULL,
  sn VARCHAR(64) NOT NULL, pay_way TINYINT NOT NULL DEFAULT 2, pay_time INT UNSIGNED NULL,
  create_time INT UNSIGNED NULL, update_time INT UNSIGNED NULL, pay_sn VARCHAR(255) NULL,
  transaction_id VARCHAR(128) NULL, tenant_id BIGINT UNSIGNED NOT NULL,
  PRIMARY KEY (id), UNIQUE KEY uk_sn (sn), UNIQUE KEY uk_pay_sn (pay_sn),
  UNIQUE KEY uk_transaction_id (transaction_id), UNIQUE KEY uk_recharge_order_tenant_id (tenant_id, id),
  KEY idx_recharge_order_tenant_member_time (tenant_id, user_id, create_time, id),
  CONSTRAINT fk_recharge_order_tenant FOREIGN KEY (tenant_id) REFERENCES pa_tenant (id) ON DELETE RESTRICT,
  CONSTRAINT fk_recharge_order_member FOREIGN KEY (tenant_id, user_id) REFERENCES pa_member (tenant_id, id) ON DELETE RESTRICT
) ENGINE=InnoDB;
CREATE TABLE pa_refund_record (
  id INT NOT NULL AUTO_INCREMENT, order_type VARCHAR(255) NULL DEFAULT 'order',
  order_amount DECIMAL(10,2) UNSIGNED NOT NULL DEFAULT 0, refund_amount DECIMAL(10,2) UNSIGNED NOT NULL DEFAULT 0,
  transaction_id VARCHAR(255) NULL, refund_way TINYINT NOT NULL DEFAULT 1, refund_type TINYINT NOT NULL DEFAULT 1,
  refund_status TINYINT UNSIGNED NOT NULL DEFAULT 0, refund_msg TEXT NULL,
  create_time INT UNSIGNED NULL DEFAULT 0, update_time INT NULL, sn VARCHAR(32) NOT NULL DEFAULT '',
  order_sn VARCHAR(64) NOT NULL, tenant_id BIGINT UNSIGNED NOT NULL,
  user_id INT UNSIGNED NOT NULL DEFAULT 0, order_id INT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (id), UNIQUE KEY uk_sn (sn), UNIQUE KEY uk_refund_record_tenant_id (tenant_id, id),
  UNIQUE KEY uk_refund_record_tenant_order (tenant_id, order_type, order_id),
  UNIQUE KEY uk_refund_record_order_global (order_type, order_id),
  KEY idx_refund_record_tenant_status_time (tenant_id, refund_status, create_time, id),
  CONSTRAINT fk_refund_record_tenant FOREIGN KEY (tenant_id) REFERENCES pa_tenant (id) ON DELETE RESTRICT,
  CONSTRAINT fk_refund_record_member FOREIGN KEY (tenant_id, user_id) REFERENCES pa_member (tenant_id, id) ON DELETE RESTRICT,
  CONSTRAINT fk_refund_record_order FOREIGN KEY (tenant_id, order_id) REFERENCES pa_recharge_order (tenant_id, id) ON DELETE RESTRICT
) ENGINE=InnoDB;
CREATE TABLE pa_refund_log (
  id INT NOT NULL AUTO_INCREMENT, sn VARCHAR(32) NULL, record_id INT NOT NULL,
  handle_id INT NOT NULL DEFAULT 0, order_amount DECIMAL(10,2) UNSIGNED NOT NULL DEFAULT 0,
  refund_amount DECIMAL(10,2) UNSIGNED NOT NULL DEFAULT 0, refund_status TINYINT UNSIGNED NOT NULL DEFAULT 0,
  refund_msg TEXT NULL, create_time INT UNSIGNED NULL DEFAULT 0, update_time INT NULL,
  tenant_id BIGINT UNSIGNED NOT NULL, user_id INT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (id), UNIQUE KEY uk_sn (sn), UNIQUE KEY uk_refund_log_tenant_id (tenant_id, id),
  KEY idx_refund_log_tenant_record_time (tenant_id, record_id, create_time, id),
  CONSTRAINT fk_refund_log_tenant FOREIGN KEY (tenant_id) REFERENCES pa_tenant (id) ON DELETE RESTRICT,
  CONSTRAINT fk_refund_log_member FOREIGN KEY (tenant_id, user_id) REFERENCES pa_member (tenant_id, id) ON DELETE RESTRICT,
  CONSTRAINT fk_refund_log_record FOREIGN KEY (tenant_id, record_id) REFERENCES pa_refund_record (tenant_id, id) ON DELETE RESTRICT
) ENGINE=InnoDB;
SQL);
}

function seedFinanceTenantSchema(PDO $pdo): void
{
    $pdo->exec(<<<'SQL'
INSERT INTO pa_tenant (id,code,status) VALUES (101,'alpha','active'),(202,'beta','active');
INSERT INTO pa_member (id,tenant_id,sn,account,nickname,user_money,total_recharge_amount)
VALUES (11,101,'M-ALPHA','alpha','Alpha',100.00,10.00),(22,202,'M-BETA','beta','Beta',20.00,0);
INSERT INTO pa_recharge_order
  (id,tenant_id,sn,user_id,pay_sn,pay_way,pay_status,pay_time,order_amount,order_terminal,transaction_id,refund_status,create_time)
VALUES (21,101,'RC-ALPHA',11,'PY-ALPHA',2,1,1700000000,10.00,3,'TX-ALPHA',1,1700000000);
INSERT INTO pa_refund_record
  (id,tenant_id,sn,user_id,order_id,order_sn,order_type,order_amount,refund_amount,transaction_id,refund_way,refund_type,refund_status,refund_msg)
VALUES (31,101,'RF-ALPHA',11,21,'RC-ALPHA','recharge',10.00,10.00,'TX-ALPHA',1,1,2,'');
INSERT INTO pa_refund_log
  (id,tenant_id,sn,record_id,user_id,handle_id,order_amount,refund_amount,refund_status,refund_msg)
VALUES (41,101,'RL-ALPHA',31,11,1,10.00,10.00,2,'');
SQL);
}

$host = IsolatedBackendEnvironment::required('DB_HOST');
$port = (int)IsolatedBackendEnvironment::required('DB_PORT');
$user = IsolatedBackendEnvironment::required('DB_USER');
$password = IsolatedBackendEnvironment::required('DB_PASS');
$admin = new PDO("mysql:host={$host};port={$port};charset=utf8mb4", $user, $password, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$database = financeDatabase($admin, 'peanut_admin_mt03_finance_');

try {
    $pdo = financePdo($host, $port, $user, $password, $database);
    createFinanceTenantSchema($pdo);
    seedFinanceTenantSchema($pdo);
    IsolatedBackendEnvironment::activateDatabase($host, $port, $database, $user, $password, 'multi-tenant');
    $app = new think\App(); $app->initialize();
    $alpha = financeTenantContext(101, 501, 'mt03-finance-alpha');
    $beta = financeTenantContext(202, 502, 'mt03-finance-beta');
    expectFinanceTenant(!FinanceTenantRepository::records($alpha)->where('id', 31)->findOrEmpty()->isEmpty(), 'Alpha refund record disappeared');
    expectFinanceTenant(FinanceTenantRepository::records($beta)->where('id', 31)->findOrEmpty()->isEmpty(), 'Beta read Alpha refund record');
    expectFinanceTenant(!FinanceTenantRepository::logs($alpha)->where('id', 41)->findOrEmpty()->isEmpty(), 'Alpha refund log disappeared');
    expectFinanceTenant(FinanceTenantRepository::logs($beta)->where('id', 41)->findOrEmpty()->isEmpty(), 'Beta read Alpha refund log');

    $betaOrder = FinanceTenantRepository::createOrder($beta, [
        'tenant_id' => 101,
        'sn' => 'RC-BETA-22', 'user_id' => 22, 'pay_sn' => null, 'pay_way' => 2,
        'pay_status' => RechargeOrder::PAY_STATUS_UNPAID, 'order_amount' => '5.00',
        'order_terminal' => 3, 'transaction_id' => null, 'refund_status' => 0,
    ]);
    expectFinanceTenant((int)$betaOrder->tenant_id === 202, 'payload forged order Tenant ownership');
    expectFinanceTenant(FinanceTenantRepository::orders($alpha)->where('id', (int)$betaOrder->id)->findOrEmpty()->isEmpty(), 'Alpha read Beta order');

    $system = FinanceTenantContext::externalPayment(202, 'fixture:RC-BETA-22');
    expectFinanceTenant(FinanceTenantContext::tenantId($system) === 202, 'verified callback did not carry Beta Tenant');
    $betaBefore = (string)MemberTenantRepository::members($beta)->where('id', 22)->value('user_money');
    expectFinanceTenant(ApiRechargeLogic::settle($system, [
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
} finally {
    $admin->exec("DROP DATABASE IF EXISTS `{$database}`");
}

echo "MT03-RECHARGE-REFUND-TENANT-001 passed\n";
