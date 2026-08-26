<?php
declare(strict_types=1);

use app\adminapi\http\middleware\OperationLogMiddleware;
use app\adminapi\logic\log\OperationLogLogic;
use app\adminapi\service\OperationLogService;
use app\common\service\audit\OperationLogDiagnostics;
use app\common\service\audit\OperationLogTenantContext;
use PeanutAdmin\Kernel\Auth\TenantContext;
use PeanutAdmin\Kernel\Auth\ValidatedTenantSession;

require dirname(__DIR__, 2) . '/vendor/autoload.php';
require __DIR__ . '/../Support/IsolatedBackendEnvironment.php';

function expectOperationTenant(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function operationTenantContext(int $tenantId, int $memberId, string $requestId): TenantContext
{
    return TenantContext::fromValidatedSession(new ValidatedTenantSession(
        $memberId,
        '01JMT03AUDITLOG' . str_pad((string)$memberId, 11, '0', STR_PAD_LEFT),
        $tenantId,
        $memberId + 10000,
        $memberId,
        'admin-web',
        new DateTimeImmutable('2031-01-01T00:00:00Z'),
        1,
    ), $requestId);
}

function createOperationTenantSchema(PDO $pdo): void
{
    $pdo->exec(<<<'SQL'
CREATE TABLE pa_tenant (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  status VARCHAR(32) NOT NULL,
  PRIMARY KEY (id)
) ENGINE=InnoDB;
CREATE TABLE pa_operation_log (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  admin_id INT UNSIGNED NOT NULL DEFAULT 0,
  username VARCHAR(50) NOT NULL DEFAULT '',
  ip VARCHAR(50) NOT NULL DEFAULT '',
  uri VARCHAR(200) NOT NULL DEFAULT '',
  method VARCHAR(10) NOT NULL DEFAULT '',
  request_id VARCHAR(128) NOT NULL DEFAULT '',
  params TEXT,
  create_time INT UNSIGNED NOT NULL DEFAULT 0,
  tenant_id BIGINT UNSIGNED NOT NULL,
  PRIMARY KEY (id), UNIQUE KEY uk_operation_log_tenant_id (tenant_id, id),
  KEY idx_operation_log_tenant_created (tenant_id, create_time, id),
  CONSTRAINT fk_operation_log_tenant FOREIGN KEY (tenant_id) REFERENCES pa_tenant (id) ON DELETE RESTRICT
) ENGINE=InnoDB;
SQL);
}

$serverRoot = dirname(__DIR__, 2);
$host = IsolatedBackendEnvironment::required('DB_HOST');
$port = (int)IsolatedBackendEnvironment::required('DB_PORT');
$user = IsolatedBackendEnvironment::required('DB_USER');
$password = IsolatedBackendEnvironment::required('DB_PASS');
$runId = strtolower(bin2hex(random_bytes(5)));
$database = 'peanut_admin_mt03_audit_' . $runId;

$admin = new PDO(
    "mysql:host={$host};port={$port};charset=utf8mb4",
    $user,
    $password,
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::MYSQL_ATTR_MULTI_STATEMENTS => true]
);
$admin->exec("CREATE DATABASE `{$database}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");

$exportedPath = null;
try {
    $pdo = new PDO(
        "mysql:host={$host};port={$port};dbname={$database};charset=utf8mb4",
        $user,
        $password,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES => false, PDO::MYSQL_ATTR_MULTI_STATEMENTS => true]
    );
    createOperationTenantSchema($pdo);
    $pdo->exec("INSERT INTO pa_tenant (id, status) VALUES (101, 'active'), (202, 'active')");
    $pdo->exec("INSERT INTO pa_operation_log (tenant_id, username, uri, method) VALUES (101, 'alpha-seed', 'seed/read', 'GET')");
    IsolatedBackendEnvironment::activateDatabase($host, $port, $database, $user, $password);
    $app = new think\App();
    $app->initialize();

    $alpha = operationTenantContext(101, 501, 'mt03-audit-alpha-' . $runId);
    $beta = operationTenantContext(202, 502, 'mt03-audit-beta-' . $runId);
    try {
        OperationLogTenantContext::member(new stdClass());
        throw new RuntimeException('missing TenantContext unexpectedly succeeded');
    } catch (Throwable $exception) {
        expectOperationTenant($exception->getMessage() !== '', 'missing context denial lost its shape');
    }
    expectOperationTenant(
        OperationLogDiagnostics::attributes(null) === [
            'scope' => 'unavailable', 'tenant_id' => null, 'request_id' => '',
        ],
        'unavailable diagnostics were attributed to a default Tenant'
    );
    expectOperationTenant(
        OperationLogDiagnostics::attributes($alpha)['tenant_id'] === 101,
        'trusted diagnostics lost Tenant attribution'
    );

    $handlerCalled = false;
    $missingRequest = new class {
        public function method(): string { return 'POST'; }
    };
    try {
        (new OperationLogMiddleware())->handle($missingRequest, function () use (&$handlerCalled): void {
            $handlerCalled = true;
        });
        throw new RuntimeException('middleware accepted a write without TenantContext');
    } catch (Throwable $exception) {
        expectOperationTenant(!$handlerCalled, 'missing context reached the business handler');
        expectOperationTenant(
            (int)$pdo->query('SELECT COUNT(*) FROM pa_operation_log')->fetchColumn() === 1,
            'missing context produced an audit database side effect'
        );
    }

    OperationLogService::record($alpha, 1, 'alpha', '127.0.0.1', 'same/write', 'POST', [
        'tenant_id' => 202,
        'marker' => 'alpha-only-' . $runId,
    ]);
    OperationLogService::record($beta, 2, 'beta', '127.0.0.2', 'same/write', 'POST', [
        'tenant_id' => 101,
        'marker' => 'beta-only-' . $runId,
    ]);
    expectOperationTenant(
        (int)$pdo->query("SELECT tenant_id FROM pa_operation_log WHERE username = 'alpha'")->fetchColumn() === 101,
        'payload forged Alpha audit ownership'
    );
    expectOperationTenant(
        (int)$pdo->query("SELECT tenant_id FROM pa_operation_log WHERE username = 'beta'")->fetchColumn() === 202,
        'payload forged Beta audit ownership'
    );

    $alphaList = OperationLogLogic::lists($alpha, ['tenant_id' => 202, 'uri' => 'same/write']);
    $betaList = OperationLogLogic::lists($beta, ['tenant_id' => 101, 'uri' => 'same/write']);
    expectOperationTenant($alphaList['count'] === 1 && $alphaList['lists'][0]['username'] === 'alpha', 'Alpha list leaked or lost audit rows');
    expectOperationTenant($betaList['count'] === 1 && $betaList['lists'][0]['username'] === 'beta', 'Beta list leaked or lost audit rows');

    $betaId = (int)$pdo->query("SELECT id FROM pa_operation_log WHERE username = 'beta'")->fetchColumn();
    foreach ([$betaId, 999999] as $target) {
        try {
            OperationLogLogic::detail($alpha, $target);
            throw new RuntimeException('cross/missing audit detail unexpectedly succeeded');
        } catch (InvalidArgumentException $exception) {
            expectOperationTenant($exception->getMessage() === '操作日志不存在', 'audit detail denial enumerated Tenant ownership');
        }
    }

    $export = OperationLogLogic::lists($alpha, [
        'tenant_id' => 202,
        'uri' => 'same/write',
        'export' => 2,
        'file_name' => 'tenant-audit-' . $runId,
    ]);
    $exportUrl = (string)$export['url'];
    $storageOffset = strpos($exportUrl, 'storage/exports/');
    expectOperationTenant($storageOffset !== false, 'Alpha export URL lost its storage path');
    $exportUri = substr($exportUrl, $storageOffset);
    expectOperationTenant(
        str_starts_with($exportUri, 'storage/exports/tenants/v1/101/operation-logs/'),
        'Alpha export escaped its Tenant namespace'
    );
    $exportedPath = $serverRoot . '/public/' . $exportUri;
    expectOperationTenant(is_file($exportedPath), 'Alpha export file was not created');
    $zip = new ZipArchive();
    expectOperationTenant($zip->open($exportedPath) === true, 'Alpha export is not a readable XLSX');
    $sheet = (string)$zip->getFromName('xl/worksheets/sheet1.xml');
    $zip->close();
    expectOperationTenant(str_contains($sheet, 'alpha-only-' . $runId), 'Alpha export lost its own audit row');
    expectOperationTenant(!str_contains($sheet, 'beta-only-' . $runId), 'Alpha export leaked Beta audit content');

    $cleared = OperationLogLogic::clear($alpha, 1, 'alpha', '127.0.0.1');
    expectOperationTenant($cleared === 2, 'Alpha clear did not count only Alpha rows');
    expectOperationTenant(
        (int)$pdo->query('SELECT COUNT(*) FROM pa_operation_log WHERE tenant_id = 101')->fetchColumn() === 1,
        'Alpha clear did not preserve its tenant-scoped tombstone'
    );
    expectOperationTenant(
        (int)$pdo->query("SELECT COUNT(*) FROM pa_operation_log WHERE tenant_id = 202 AND username = 'beta'")->fetchColumn() === 1,
        'Alpha clear touched Beta audit rows'
    );

    $recordSignature = new ReflectionMethod(OperationLogService::class, 'record');
    expectOperationTenant(
        $recordSignature->getParameters()[0]->getType()?->getName() === TenantContext::class,
        'operation log write boundary does not require TenantContext'
    );

    echo "MT03-OPERATION-LOG-TENANT-001 passed\n";
} finally {
    if (is_string($exportedPath) && is_file($exportedPath)) {
        unlink($exportedPath);
    }
    $admin->exec("DROP DATABASE IF EXISTS `{$database}`");
}
