<?php
declare(strict_types=1);

use app\adminapi\logic\system\SystemLogic;
use app\adminapi\service\OperationLogService;
use app\common\service\permission\RegisteredAdminPermissionPolicy;

require dirname(__DIR__, 2) . '/bootstrap/environment.php';
require dirname(__DIR__, 2) . '/vendor/autoload.php';

function expectOpsHost(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$serverRoot = dirname(__DIR__, 2);
$repositoryRoot = dirname($serverRoot);
$app = new think\App();
$app->initialize();

$permissions = ['log/lists', 'log/clear', 'system/info', 'system/clearcache'];
$policy = new RegisteredAdminPermissionPolicy();
foreach ($permissions as $permission) {
    expectOpsHost(
        $policy->canAccess(false, $permission, $permissions, []) === false,
        'registered unowned operation must fail closed: ' . $permission
    );
}

$migration = (string)file_get_contents(
    $serverRoot . '/database/init.sql'
);
foreach ($permissions as $permission) {
    expectOpsHost(
        str_contains(strtolower($migration), "'" . $permission . "'"),
        'operation permission is not registered: ' . $permission
    );
}

$safeValue = 'visible-value';
$sensitiveValue = 'must-not-appear';
$redacted = OperationLogService::redactSensitive([
    'safe' => $safeValue,
    'jobs_code' => 'ops_job',
    'password' => $sensitiveValue,
    'mch_key' => $sensitiveValue,
    'api_v3_key' => $sensitiveValue,
    'wx_pay_cert_path' => $sensitiveValue,
    'verification_code' => $sensitiveValue,
    'nested' => [
        'Authorization' => $sensitiveValue,
        'public_key' => $safeValue,
    ],
]);
expectOpsHost(($redacted['safe'] ?? null) === $safeValue, 'safe value must remain visible');
expectOpsHost(($redacted['jobs_code'] ?? null) === 'ops_job', 'business code must not be over-redacted');
expectOpsHost(($redacted['nested']['public_key'] ?? null) === $safeValue, 'public key must remain visible');
foreach (['password', 'mch_key', 'api_v3_key', 'wx_pay_cert_path', 'verification_code'] as $key) {
    expectOpsHost(($redacted[$key] ?? null) === '******', 'sensitive field was not redacted: ' . $key);
}
expectOpsHost(
    ($redacted['nested']['Authorization'] ?? null) === '******',
    'nested authorization was not redacted'
);

$serialized = OperationLogService::serializeParams($redacted);
expectOpsHost(!str_contains($serialized, $sensitiveValue), 'serialized log leaked a sensitive value');
$oversized = OperationLogService::serializeParams(['payload' => str_repeat('x', 70000)]);
expectOpsHost(
    $oversized === '{"_redacted":"payload_unavailable"}',
    'oversized payload must fail closed to bounded metadata'
);

$info = SystemLogic::getInfo();
expectOpsHost(array_keys($info) === ['server', 'env', 'auth'], 'maintenance probe shape changed');
expectOpsHost(($info['env'][0]['require'] ?? null) === '8.3版本以上', 'PHP requirement must match Composer');
foreach ($info['auth'] as $directory) {
    expectOpsHost(
        in_array((int)($directory['status'] ?? -1), [0, 1], true),
        'directory probe must return a boolean status'
    );
}
$encodedInfo = json_encode($info, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
expectOpsHost(!str_contains($encodedInfo, root_path()), 'maintenance probe must not expose absolute paths');

$systemSource = (string)file_get_contents($serverRoot . '/app/adminapi/logic/system/SystemLogic.php');
$probeStart = strpos($systemSource, 'public static function getInfo');
$probeEnd = strpos($systemSource, 'public static function clearCache');
expectOpsHost($probeStart !== false && $probeEnd !== false, 'maintenance probe source was not found');
$probeSource = substr($systemSource, $probeStart, $probeEnd - $probeStart);
foreach (['check_dir_write', 'file_put_contents', 'touch(', 'mkdir(', 'unlink(', 'del_target_dir', 'Cache::'] as $mutation) {
    expectOpsHost(!str_contains($probeSource, $mutation), 'maintenance probe must stay read-only: ' . $mutation);
}

$middlewareSource = (string)file_get_contents(
    $serverRoot . '/app/adminapi/http/middleware/OperationLogMiddleware.php'
);
$logicSource = (string)file_get_contents($serverRoot . '/app/adminapi/logic/log/OperationLogLogic.php');
$serviceSource = (string)file_get_contents($serverRoot . '/app/adminapi/service/OperationLogService.php');
$auditHostSource = (string)file_get_contents($serverRoot . '/app/common/service/audit/AuditContractHost.php');
$projectionSource = (string)file_get_contents($serverRoot . '/app/common/service/audit/OperationLogProjection.php');
expectOpsHost(!str_contains($middlewareSource, 'OperationLog::create'), 'middleware must use the unique log service');
expectOpsHost(!str_contains($logicSource, 'OperationLog::create'), 'clear must use the unique log service');
expectOpsHost(str_contains($serviceSource, 'AuditContractHost'), 'log service must use the unified audit host');
expectOpsHost(str_contains($auditHostSource, 'OperationLogProjection'), 'audit host must use the operation log projection');
expectOpsHost(
    substr_count($projectionSource, 'OperationLogTenantRepository::createForTenant') === 1,
    'operation log projection must be the unique OperationLog writer'
);
expectOpsHost(str_contains($logicSource, 'Db::transaction'), 'log clear must be transactional');
expectOpsHost(str_contains($logicSource, "'log/clear'"), 'log clear must retain an audit tombstone');
expectOpsHost(!str_contains($serviceSource, 'PeanutAdmin\\OpsConsole'), 'application log owner must not deep import core');

echo "PB04-OPS-HOST-001 passed\n";
