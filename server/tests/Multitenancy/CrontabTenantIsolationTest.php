<?php
declare(strict_types=1);

use app\Modules\Official\Task\Service\CrontabLogic;
use app\common\enum\CrontabEnum;
use app\common\service\CrontabCommandService;
use app\common\service\crontab\CrontabSchedulerService;
use app\common\service\crontab\CrontabTenantContext;
use app\common\service\crontab\CrontabTenantLock;
use app\common\service\crontab\CrontabTenantRepository;
use PeanutAdmin\Kernel\Tenancy\ScheduledTenantContext;
use PeanutAdmin\Kernel\Tenancy\TenantScope;
use PeanutAdmin\Kernel\Auth\TenantContext;
use PeanutAdmin\Kernel\Auth\ValidatedTenantSession;
use think\facade\Db;

require dirname(__DIR__, 2) . '/vendor/autoload.php';
require __DIR__ . '/../Support/IsolatedBackendEnvironment.php';

function expectCrontabTenant(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function crontabTenantContext(int $tenantId, int $memberId, string $requestId): TenantContext
{
    return TenantContext::fromValidatedSession(new ValidatedTenantSession(
        $memberId,
        '01JMT03CRONTAB' . str_pad((string)$memberId, 12, '0', STR_PAD_LEFT),
        $tenantId,
        $memberId + 10000,
        $memberId,
        'admin-web',
        new DateTimeImmutable('2031-01-01T00:00:00Z'),
        1,
    ), $requestId);
}

function createCrontabTenantSchema(PDO $pdo): void
{
    $pdo->exec(<<<'SQL'
CREATE TABLE pa_tenant (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  status VARCHAR(32) NOT NULL,
  PRIMARY KEY (id)
) ENGINE=InnoDB;
CREATE TABLE pa_crontab (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name VARCHAR(100) NOT NULL DEFAULT '',
  type TINYINT NOT NULL DEFAULT 1,
  command VARCHAR(100) NOT NULL DEFAULT '',
  params VARCHAR(255) NOT NULL DEFAULT '',
  status TINYINT NOT NULL DEFAULT 2,
  expression VARCHAR(100) NOT NULL DEFAULT '',
  error VARCHAR(255) NOT NULL DEFAULT '',
  last_time INT UNSIGNED NOT NULL DEFAULT 0,
  time DECIMAL(10,2) NOT NULL DEFAULT 0,
  max_time DECIMAL(10,2) NOT NULL DEFAULT 0,
  sort INT UNSIGNED NOT NULL DEFAULT 0,
  remark VARCHAR(255) NOT NULL DEFAULT '',
  create_time INT UNSIGNED NOT NULL DEFAULT 0,
  update_time INT UNSIGNED NOT NULL DEFAULT 0,
  delete_time INT UNSIGNED NULL DEFAULT NULL,
  tenant_id BIGINT UNSIGNED NOT NULL,
  PRIMARY KEY (id), KEY idx_status (status),
  UNIQUE KEY uk_crontab_tenant_id (tenant_id, id),
  KEY idx_crontab_tenant_status_last (tenant_id, status, last_time, id),
  CONSTRAINT fk_crontab_tenant FOREIGN KEY (tenant_id) REFERENCES pa_tenant (id) ON DELETE RESTRICT
) ENGINE=InnoDB;
SQL);
}

$serverRoot = dirname(__DIR__, 2);
$host = IsolatedBackendEnvironment::required('DB_HOST');
$port = (int)IsolatedBackendEnvironment::required('DB_PORT');
$user = IsolatedBackendEnvironment::required('DB_USER');
$password = IsolatedBackendEnvironment::required('DB_PASS');
$runId = strtolower(bin2hex(random_bytes(5)));
$database = 'peanut_admin_mt03_crontab_' . $runId;
$admin = new PDO(
    "mysql:host={$host};port={$port};charset=utf8mb4",
    $user,
    $password,
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::MYSQL_ATTR_MULTI_STATEMENTS => true]
);
$admin->exec("CREATE DATABASE `{$database}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");

try {
    $pdo = new PDO(
        "mysql:host={$host};port={$port};dbname={$database};charset=utf8mb4",
        $user,
        $password,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES => false, PDO::MYSQL_ATTR_MULTI_STATEMENTS => true]
    );
    createCrontabTenantSchema($pdo);
    $pdo->exec("INSERT INTO pa_tenant (id, status) VALUES (101, 'active'), (202, 'active')");
    $pdo->exec("INSERT INTO pa_crontab (id, tenant_id, name, command, status, expression) VALUES (11, 101, 'Alpha seed', 'crontab:demo', 1, '* * * * *')");
    IsolatedBackendEnvironment::activateDatabase($host, $port, $database, $user, $password);
    $app = new think\App();
    $app->initialize();

    $alpha = crontabTenantContext(101, 501, 'mt03-crontab-alpha-' . $runId);
    $beta = crontabTenantContext(202, 502, 'mt03-crontab-beta-' . $runId);
    try {
        CrontabTenantContext::member(new stdClass());
        throw new RuntimeException('missing TenantContext unexpectedly succeeded');
    } catch (Throwable $exception) {
        expectCrontabTenant($exception->getMessage() !== '', 'missing context denial lost its shape');
    }

    $task = [
        'tenant_id' => 999,
        'name' => 'Same task',
        'type' => 1,
        'command' => 'crontab:demo',
        'params' => '',
        'status' => CrontabEnum::START,
        'expression' => '* * * * *',
        'sort' => 0,
        'remark' => 'MT03-CRONTAB-TENANT-ISOLATION-001',
    ];
    expectCrontabTenant(CrontabLogic::add($alpha, $task), CrontabLogic::getError());
    expectCrontabTenant(CrontabLogic::add($beta, $task), CrontabLogic::getError());
    $alphaId = (int)CrontabTenantRepository::schedules($alpha)->where('name', 'Same task')->value('id');
    $betaId = (int)CrontabTenantRepository::schedules($beta)->where('name', 'Same task')->value('id');
    expectCrontabTenant($alphaId > 0 && $betaId > 0 && $alphaId !== $betaId, 'Tenant tasks were not independently created');
    expectCrontabTenant((int)$pdo->query("SELECT tenant_id FROM pa_crontab WHERE id = {$alphaId}")->fetchColumn() === 101, 'payload forged task owner');
    expectCrontabTenant(CrontabLogic::lists($alpha, ['name' => 'Same task'])['count'] === 1, 'Alpha task list leaked or lost rows');
    expectCrontabTenant(CrontabLogic::lists($beta, ['name' => 'Same task'])['count'] === 1, 'Beta task list leaked or lost rows');
    expectCrontabTenant(CrontabLogic::detail($alpha, $betaId) === [], 'cross-Tenant task detail leaked');
    expectCrontabTenant(!CrontabLogic::delete($alpha, $betaId), 'cross-Tenant task delete succeeded');
    expectCrontabTenant(CrontabLogic::getError() === '定时任务不存在', 'cross/missing task denial enumerated owner');
    expectCrontabTenant(!CrontabLogic::delete($alpha, 999999), 'missing task delete succeeded');
    expectCrontabTenant(CrontabLogic::getError() === '定时任务不存在', 'missing task denial changed shape');

    Db::name('crontab')->where('id', $alphaId)->update(['status' => CrontabEnum::ERROR, 'error' => 'alpha']);
    Db::name('crontab')->where('id', $betaId)->update(['status' => CrontabEnum::ERROR, 'error' => 'beta']);
    expectCrontabTenant(!CrontabLogic::operate($alpha, $betaId, 'start'), 'cross-Tenant retry succeeded');
    expectCrontabTenant(CrontabLogic::operate($alpha, $alphaId, 'start'), CrontabLogic::getError());
    expectCrontabTenant((string)Db::name('crontab')->where('id', $alphaId)->value('error') === '', 'Alpha retry did not clear Alpha error');
    expectCrontabTenant((string)Db::name('crontab')->where('id', $betaId)->value('error') === 'beta', 'Alpha retry cleared Beta error');

    $alphaScope = TenantScope::fromTrustedContext(101, 'fixture:' . $runId . ':alpha');
    $betaScope = TenantScope::fromTrustedContext(202, 'fixture:' . $runId . ':beta');
    expectCrontabTenant(CrontabTenantLock::name($alphaScope, 77) !== CrontabTenantLock::name($betaScope, 77), 'same job ID shares a Tenant lock namespace');
    $lockAlpha = new PDO(
        "mysql:host={$host};port={$port};dbname={$database};charset=utf8mb4",
        $user,
        $password,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES => false]
    );
    $lockBeta = new PDO(
        "mysql:host={$host};port={$port};dbname={$database};charset=utf8mb4",
        $user,
        $password,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES => false]
    );
    $lockStatement = $lockAlpha->prepare('SELECT GET_LOCK(?, 0)');
    $lockStatement->execute([CrontabTenantLock::name($alphaScope, 77)]);
    expectCrontabTenant((int)$lockStatement->fetchColumn() === 1, 'Alpha advisory lock was not acquired');
    $lockStatement = $lockBeta->prepare('SELECT GET_LOCK(?, 0)');
    $lockStatement->execute([CrontabTenantLock::name($betaScope, 77)]);
    expectCrontabTenant((int)$lockStatement->fetchColumn() === 1, 'Beta advisory lock was blocked by Alpha');
    $releaseStatement = $lockAlpha->prepare('SELECT RELEASE_LOCK(?)');
    $releaseStatement->execute([CrontabTenantLock::name($alphaScope, 77)]);
    $releaseStatement = $lockBeta->prepare('SELECT RELEASE_LOCK(?)');
    $releaseStatement->execute([CrontabTenantLock::name($betaScope, 77)]);

    $dispatches = [];
    CrontabSchedulerService::useDispatcherForTest(static function (string $command, array $params) use (&$dispatches): void {
        $scope = ScheduledTenantContext::require();
        $dispatches[] = [$command, $scope->tenantId(), $scope->contextIdentity(), $params];
    });
    $alphaItem = CrontabTenantRepository::find($alpha, $alphaId)?->getData() ?? [];
    CrontabSchedulerService::start($alphaScope, $alphaItem);
    expectCrontabTenant(count($dispatches) === 1 && $dispatches[0][1] === 101, 'dispatch did not restore Alpha scope');
    expectCrontabTenant(ScheduledTenantContext::current() === null, 'scheduled scope leaked after handler');

    $sideEffects = count($dispatches);
    $forgedOwner = $alphaItem;
    $forgedOwner['tenant_id'] = 202;
    try {
        CrontabSchedulerService::start($alphaScope, $forgedOwner);
        throw new RuntimeException('owner mismatch unexpectedly succeeded');
    } catch (RuntimeException $exception) {
        expectCrontabTenant($exception->getMessage() === 'Scheduled job owner is unavailable', 'owner mismatch denial changed');
    }
    expectCrontabTenant(count($dispatches) === $sideEffects, 'owner mismatch reached handler');

    $forgedPayload = $alphaItem;
    $forgedPayload['command'] = 'refund:reconcile';
    $forgedPayload['params'] = '--tenant_id=202';
    CrontabSchedulerService::start($alphaScope, $forgedPayload);
    expectCrontabTenant(count($dispatches) === $sideEffects + 1, 'persisted Tenant-aware job was not dispatched');
    expectCrontabTenant($dispatches[$sideEffects][0] === 'crontab:demo' && $dispatches[$sideEffects][3] === [], 'payload fields overrode the persisted job envelope');
    $sideEffects = count($dispatches);

    Db::name('crontab')->where('id', $alphaId)->update(['command' => 'generator:cleanup', 'status' => CrontabEnum::START, 'error' => '']);
    $blockedItem = CrontabTenantRepository::find($alpha, $alphaId)?->getData() ?? [];
    CrontabSchedulerService::start($alphaScope, $blockedItem);
    expectCrontabTenant(count($dispatches) === $sideEffects, 'unadopted command reached handler');
    expectCrontabTenant(str_contains((string)Db::name('crontab')->where('id', $alphaId)->value('error'), '尚未采用可信租户上下文'), 'unadopted command did not fail closed');
    expectCrontabTenant(ScheduledTenantContext::current() === null, 'blocked command installed a scope');
    CrontabSchedulerService::useDispatcherForTest(null);

    try {
        CrontabCommandService::assertTenantAware('generator:cleanup');
        throw new RuntimeException('Generator cleanup unexpectedly became a Tenant job');
    } catch (RuntimeException $exception) {
        expectCrontabTenant(str_contains($exception->getMessage(), '尚未采用可信租户上下文'), 'Generator stop line changed');
    }
} finally {
    CrontabSchedulerService::useDispatcherForTest(null);
    $admin->exec("DROP DATABASE IF EXISTS `{$database}`");
}

echo "MT03-CRONTAB-TENANT-ISOLATION-001 passed\n";
