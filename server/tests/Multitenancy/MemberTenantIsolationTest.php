<?php
declare(strict_types=1);

use app\adminapi\logic\finance\AccountLogLogic as AdminAccountLogLogic;
use app\adminapi\logic\member\MemberLogic;
use app\adminapi\logic\member\MemberTagLogic;
use app\api\logic\AccountLogLogic as ApiAccountLogLogic;
use app\common\enum\AccountLogEnum;
use app\common\logic\AccountLogLogic;
use app\common\service\MemberBalanceService;
use app\common\service\member\MemberTenantContext;
use app\common\service\member\MemberTenantRepository;
use PeanutAdmin\Kernel\Auth\TenantContext;
use PeanutAdmin\Kernel\Auth\ValidatedTenantSession;

require dirname(__DIR__, 2) . '/vendor/autoload.php';

function expectMemberTenant(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function memberTenantContext(int $tenantId, int $memberId, string $requestId): TenantContext
{
    return TenantContext::fromValidatedSession(new ValidatedTenantSession(
        $memberId,
        '01JMT03MEMBER' . str_pad((string)$memberId, 13, '0', STR_PAD_LEFT),
        $tenantId,
        $memberId + 10000,
        $memberId,
        'admin-web',
        new DateTimeImmutable('2031-01-01T00:00:00Z'),
        1,
    ), $requestId);
}

function createMemberTenantSchema(PDO $pdo, bool $withTenant = true): void
{
    if ($withTenant) {
        $pdo->exec("CREATE TABLE pa_tenant (id BIGINT UNSIGNED NOT NULL, status VARCHAR(32) NOT NULL, PRIMARY KEY (id)) ENGINE=InnoDB");
    }
    $pdo->exec(<<<'SQL'
CREATE TABLE pa_member (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT, sn VARCHAR(20) NOT NULL DEFAULT '',
  account VARCHAR(50) NOT NULL DEFAULT '', password VARCHAR(100) NOT NULL DEFAULT '',
  nickname VARCHAR(50) NOT NULL DEFAULT '', avatar VARCHAR(255) NOT NULL DEFAULT '', real_name VARCHAR(32) NOT NULL DEFAULT '',
  mobile VARCHAR(20) NOT NULL DEFAULT '', mobile_unique VARCHAR(20) GENERATED ALWAYS AS (NULLIF(mobile,'')) STORED,
  channel TINYINT UNSIGNED NOT NULL DEFAULT 0, email VARCHAR(100) NOT NULL DEFAULT '', sex TINYINT NOT NULL DEFAULT 0,
  birthday DATE NULL, status TINYINT NOT NULL DEFAULT 1, login_time INT UNSIGNED NOT NULL DEFAULT 0,
  login_ip VARCHAR(45) NOT NULL DEFAULT '', is_new_user TINYINT NOT NULL DEFAULT 0,
  user_money DECIMAL(10,2) UNSIGNED NOT NULL DEFAULT 0,
  total_recharge_amount DECIMAL(10,2) UNSIGNED NOT NULL DEFAULT 0, points INT UNSIGNED NOT NULL DEFAULT 0,
  create_time INT UNSIGNED NOT NULL DEFAULT 0, update_time INT UNSIGNED NOT NULL DEFAULT 0, delete_time INT UNSIGNED NULL,
  PRIMARY KEY (id), UNIQUE KEY uk_sn (sn), UNIQUE KEY uk_mobile_nonempty (mobile_unique),
  KEY idx_account (account), KEY idx_mobile (mobile), KEY idx_status (status), KEY idx_channel (channel), KEY idx_create_time (create_time)
) ENGINE=InnoDB;
CREATE TABLE pa_member_tag (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT, name VARCHAR(50) NOT NULL DEFAULT '', remark VARCHAR(255) NOT NULL DEFAULT '',
  create_time INT UNSIGNED NOT NULL DEFAULT 0, update_time INT UNSIGNED NOT NULL DEFAULT 0, delete_time INT UNSIGNED NULL,
  PRIMARY KEY (id), UNIQUE KEY uk_name (name)
) ENGINE=InnoDB;
CREATE TABLE pa_member_tag_relation (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT, member_id INT UNSIGNED NOT NULL DEFAULT 0, tag_id INT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (id), UNIQUE KEY uk_member_tag (member_id, tag_id), KEY idx_tag_id (tag_id)
) ENGINE=InnoDB;
CREATE TABLE pa_member_balance_log (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT, sn VARCHAR(32) NOT NULL DEFAULT '', member_id INT UNSIGNED NOT NULL DEFAULT 0,
  change_object TINYINT UNSIGNED NOT NULL DEFAULT 1, change_type SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  action TINYINT UNSIGNED NOT NULL DEFAULT 1, change_amount DECIMAL(10,2) UNSIGNED NOT NULL DEFAULT 0,
  left_amount DECIMAL(10,2) UNSIGNED NOT NULL DEFAULT 0,
  source_type TINYINT NOT NULL DEFAULT 0, source_sn VARCHAR(255) NULL, remark VARCHAR(255) NULL DEFAULT '', extra TEXT NULL,
  admin_id INT UNSIGNED NOT NULL DEFAULT 0, create_time INT UNSIGNED NOT NULL DEFAULT 0, update_time INT UNSIGNED NULL, delete_time INT UNSIGNED NULL,
  PRIMARY KEY (id), UNIQUE KEY uk_sn (sn), KEY idx_member_id (member_id), KEY idx_change_type (change_type), KEY idx_create_time (create_time)
) ENGINE=InnoDB;
SQL);
}

$serverRoot = dirname(__DIR__, 2);
$host = getenv('DB_HOST') ?: '127.0.0.1';
$port = (int)(getenv('DB_PORT') ?: 3306);
$password = getenv('MYSQL_ROOT_PASSWORD') ?: 'mt02_root';
$runId = strtolower(bin2hex(random_bytes(5)));
$database = 'peanut_admin_mt03_member_' . $runId;
$missingDatabase = $database . '_missing';
$ambiguousDatabase = $database . '_ambiguous';
$admin = new PDO("mysql:host={$host};port={$port};charset=utf8mb4", 'root', $password, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::MYSQL_ATTR_MULTI_STATEMENTS => true]);
$migration = (string)file_get_contents($serverRoot . '/database/migrations/20260812_member_tenant_ownership.sql');
$fixture = (string)file_get_contents($serverRoot . '/tests/fixtures/mt03/member-tenant-legacy.sql');
expectMemberTenant($migration !== '' && $fixture !== '', 'member migration or fixture is missing');
$databases = [$database, $missingDatabase, $ambiguousDatabase];
foreach ($databases as $name) {
    $admin->exec("CREATE DATABASE `{$name}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
}

try {
    foreach ([[$missingDatabase, false, []], [$ambiguousDatabase, true, [[101, 'active'], [202, 'active']]]] as [$name, $withTenant, $tenants]) {
        $pdo = new PDO("mysql:host={$host};port={$port};dbname={$name};charset=utf8mb4", 'root', $password, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::MYSQL_ATTR_MULTI_STATEMENTS => true]);
        createMemberTenantSchema($pdo, $withTenant);
        foreach ($tenants as [$id, $status]) {
            $pdo->exec("INSERT INTO pa_tenant (id,status) VALUES ({$id},'{$status}')");
        }
        try {
            $pdo->exec($migration);
            throw new RuntimeException('member migration accepted missing or ambiguous active Tenant');
        } catch (PDOException) {
            expectMemberTenant((int)$pdo->query("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pa_member' AND COLUMN_NAME='tenant_id'")->fetchColumn() === 0, 'failed migration mutated schema before refusing');
        }
    }

    $pdo = new PDO("mysql:host={$host};port={$port};dbname={$database};charset=utf8mb4", 'root', $password, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::MYSQL_ATTR_MULTI_STATEMENTS => true]);
    createMemberTenantSchema($pdo);
    $pdo->exec("INSERT INTO pa_tenant (id,status) VALUES (101,'active')");
    $pdo->exec($fixture);
    $pdo->exec($migration);
    foreach (['pa_member', 'pa_member_tag', 'pa_member_tag_relation', 'pa_member_balance_log'] as $table) {
        expectMemberTenant((int)$pdo->query("SELECT tenant_id FROM `{$table}` LIMIT 1")->fetchColumn() === 101, "{$table} was not backfilled");
        expectMemberTenant($pdo->query("SELECT IS_NULLABLE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='{$table}' AND COLUMN_NAME='tenant_id'")->fetchColumn() === 'NO', "{$table}.tenant_id is nullable");
    }
    foreach ([
        'pa_member' => ['uk_member_tenant_sn', 'uk_member_tenant_account', 'uk_member_tenant_mobile'],
        'pa_member_tag' => ['uk_member_tag_tenant_name'],
        'pa_member_tag_relation' => ['uk_member_tag_relation_tenant_pair'],
        'pa_member_balance_log' => ['uk_member_balance_log_tenant_sn', 'uk_member_balance_log_tenant_source'],
    ] as $table => $indexes) {
        $actual = $pdo->query("SELECT DISTINCT INDEX_NAME FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='{$table}'")->fetchAll(PDO::FETCH_COLUMN);
        foreach ($indexes as $index) {
            expectMemberTenant(in_array($index, $actual, true), "{$table}.{$index} is missing");
        }
    }

    $pdo->exec("INSERT INTO pa_tenant (id,status) VALUES (202,'active')");
    putenv('PHP_DB_HOST=' . $host); putenv('PHP_DB_PORT=' . $port); putenv('PHP_DB_NAME=' . $database);
    putenv('PHP_DB_USER=root'); putenv('PHP_DB_PASS=' . $password); putenv('PHP_DB_PREFIX=pa_');
    $app = new think\App(); $app->initialize();
    set_error_handler(static function (int $severity, string $message, string $file, int $line): never {
        throw new ErrorException($message, 0, $severity, $file, $line);
    });
    set_exception_handler(static function (Throwable $exception): never {
        fwrite(STDERR, $exception::class . ': ' . $exception->getMessage() . PHP_EOL);
        exit(1);
    });
    $alpha = memberTenantContext(101, 501, 'mt03-member-alpha-' . $runId);
    $beta = memberTenantContext(202, 502, 'mt03-member-beta-' . $runId);
    try { MemberTenantContext::member(new stdClass()); throw new RuntimeException('missing TenantContext was accepted'); } catch (Throwable $e) { expectMemberTenant($e->getMessage() !== '', 'missing context denial lost shape'); }

    $betaMember = MemberTenantRepository::createMember($beta, [
        'tenant_id' => 101, 'sn' => 'M-LEGACY-11', 'account' => 'same-account', 'nickname' => 'Beta member',
        'mobile' => '13800000000', 'status' => 1, 'user_money' => 20,
    ]);
    expectMemberTenant((int)$betaMember->tenant_id === 202, 'member payload forged Tenant ownership');
    expectMemberTenant(MemberTagLogic::add($beta, ['name' => 'same-tag']), MemberTagLogic::getError());
    $betaTag = (int)MemberTenantRepository::tags($beta)->where('name', 'same-tag')->value('id');
    expectMemberTenant($betaTag > 0, 'same-name tag was not tenant-scoped');
    MemberTenantRepository::relations($beta)->insert([
        'tenant_id' => 202, 'member_id' => (int)$betaMember->id, 'tag_id' => $betaTag,
    ]);
    AccountLogLogic::add($beta, (int)$betaMember->id, AccountLogEnum::USER_MONEY_INC_ADMIN, AccountLogEnum::INC, 1.00, 'SOURCE-SAME');
    $alphaMember = (int)MemberTenantRepository::members($alpha)->where('account', 'same-account')->value('id');
    expectMemberTenant($alphaMember === 11, 'Alpha same-account member disappeared');
    expectMemberTenant(MemberLogic::detail($alpha, (int)$betaMember->id) === [], 'Alpha read Beta member');
    expectMemberTenant(MemberLogic::updateStatus($alpha, (int)$betaMember->id, 0) === false, 'Alpha updated Beta member');
    expectMemberTenant((int)MemberTenantRepository::members($beta)->where('id', (int)$betaMember->id)->value('status') === 1, 'cross-Tenant status denial mutated Beta');

    $alphaBefore = (string)MemberTenantRepository::members($alpha)->where('id', 11)->value('user_money');
    $betaBefore = (string)MemberTenantRepository::members($beta)->where('id', (int)$betaMember->id)->value('user_money');
    think\facade\Db::startTrans();
    MemberBalanceService::applyInTransaction($alpha, 11, AccountLogEnum::USER_MONEY_INC_ADMIN, AccountLogEnum::INC, 100, 'SOURCE-ALPHA-ADJUST', 'alpha');
    think\facade\Db::commit();
    expectMemberTenant((string)MemberTenantRepository::members($alpha)->where('id', 11)->value('user_money') !== $alphaBefore, 'Alpha balance did not change');
    expectMemberTenant((string)MemberTenantRepository::members($beta)->where('id', (int)$betaMember->id)->value('user_money') === $betaBefore, 'Alpha adjustment changed Beta balance');
    $adminLogs = AdminAccountLogLogic::lists($alpha, ['page_size' => 20]);
    expectMemberTenant($adminLogs !== false, 'Alpha admin ledger query failed: ' . AdminAccountLogLogic::getError());
    expectMemberTenant(count($adminLogs['lists']) === 2, 'Alpha admin ledger leaked or lost rows');
    $apiLogs = ApiAccountLogLogic::lists($beta, (int)$betaMember->id, []);
    expectMemberTenant(count($apiLogs['lists']) === 1, 'Beta API ledger leaked or lost rows');

    expectMemberTenant(MemberTagLogic::delete($alpha, $betaTag) === false, 'Alpha deleted Beta tag');
    expectMemberTenant(!MemberTenantRepository::tags($beta)->where('id', $betaTag)->findOrEmpty()->isEmpty(), 'Beta tag changed after Alpha cleanup');
    expectMemberTenant((int)MemberTenantRepository::relations($beta)->where('tag_id', $betaTag)->count() === 1, 'Alpha cleanup changed Beta relation');
    expectMemberTenant((int)MemberTenantRepository::balanceLogs($alpha)->where('source_sn', 'SOURCE-SAME')->count() === 1, 'Alpha source_sn scope mismatch');
    expectMemberTenant((int)MemberTenantRepository::balanceLogs($beta)->where('source_sn', 'SOURCE-SAME')->count() === 1, 'Beta source_sn scope mismatch');
    try {
        AccountLogLogic::add($alpha, 11, AccountLogEnum::USER_MONEY_INC_ADMIN, AccountLogEnum::INC, 1.00, 'SOURCE-SAME');
        throw new RuntimeException('same-Tenant source_sn uniqueness was lowered');
    } catch (Throwable $e) {
        $errorInfo = $e instanceof think\db\exception\PDOException
            ? ($e->getData()['PDO Error Info'] ?? [])
            : [];
        expectMemberTenant(
            ($errorInfo['SQLSTATE'] ?? '') === '23000'
                && (int)($errorInfo['Driver Error Code'] ?? 0) === 1062,
            'same-Tenant source_sn did not fail with SQLSTATE 23000 / MySQL 1062'
        );
    }
    expectMemberTenant(
        (int)MemberTenantRepository::balanceLogs($alpha)->where('source_sn', 'SOURCE-SAME')->count() === 1,
        'duplicate same-Tenant source_sn row was inserted'
    );

    foreach (['adminapi/logic/member/MemberLogic.php', 'adminapi/logic/member/MemberTagLogic.php', 'common/service/MemberBalanceService.php', 'common/logic/AccountLogLogic.php', 'common/service/member/MemberTenantContext.php', 'common/service/member/MemberTenantRepository.php'] as $relative) {
        exec(escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg($serverRoot . '/app/' . $relative), $output, $exit);
        expectMemberTenant($exit === 0, 'PHP 8.3 lint failed: ' . $relative . ' ' . implode(' ', $output));
        $output = [];
    }
} finally {
    foreach ($databases as $name) { $admin->exec("DROP DATABASE IF EXISTS `{$name}`"); }
}

echo "MT03-MEMBER-TENANT-001 passed\n";
