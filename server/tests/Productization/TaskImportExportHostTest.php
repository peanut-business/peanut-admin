<?php
declare(strict_types=1);

use app\Modules\Official\Task\Service\CrontabLogic;
use app\command\Crontab as CrontabCommand;
use app\common\enum\CrontabEnum;
use app\Modules\Official\Task\Model\Crontab;
use app\common\service\XlsxExportService;
use app\common\service\async\TaskImportExportRuntime;
use app\common\service\export\AppFileMediaGateway;
use app\common\service\export\OperationLogExportProvider;
use app\command\TenantTaskWorker;
use PeanutAdmin\ImportExport\Application\ImportExportService;
use PeanutAdmin\TaskJob\Submission\TrustedJobPublisher;
use think\facade\Db;

require dirname(__DIR__, 2) . '/vendor/autoload.php';

function expectTaskHost(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$serverRoot = dirname(__DIR__, 2);
$app = new think\App();
$app->initialize();

expectTaskHost(class_exists(TaskImportExportRuntime::class), 'application async Runtime is missing');
expectTaskHost(class_exists(TenantTaskWorker::class), 'Tenant worker command is missing');
expectTaskHost(is_subclass_of(OperationLogExportProvider::class, \PeanutAdmin\ImportExport\Contract\DataProvider::class), 'operation-log export provider does not implement the Core contract');
expectTaskHost(is_subclass_of(AppFileMediaGateway::class, \PeanutAdmin\ImportExport\File\FileMediaGateway::class), 'private file gateway does not implement the Core contract');
expectTaskHost(class_exists(TrustedJobPublisher::class) && class_exists(ImportExportService::class), 'locked Core async contracts are unavailable');

$migrationSource = (string)file_get_contents($serverRoot . '/database/init.sql');
expectTaskHost(str_contains($migrationSource, 'pa_task_job'), 'Task/Job schema is not owned by the application migration');
expectTaskHost(str_contains($migrationSource, 'pa_import_export_operation'), 'Import/Export schema is not owned by the application migration');
expectTaskHost(str_contains($migrationSource, 'pa_file_object'), 'private file metadata schema is not owned by the application migration');
expectTaskHost(!str_contains($migrationSource, 'public/storage'), 'async migration refers to public storage');

$runtimeSource = (string)file_get_contents($serverRoot . '/app/common/service/async/TaskImportExportRuntime.php');
expectTaskHost(str_contains($runtimeSource, 'TaskModuleProvider'), 'Import/Export does not use the official Task Runtime');
expectTaskHost(!str_contains($runtimeSource, 'PdoTaskJobRepository'), 'Import/Export bypasses the official Task Runtime repository boundary');
expectTaskHost(!str_contains($runtimeSource, 'TrustedJobPublisher'), 'Import/Export bypasses the official Task Runtime publisher boundary');
$taskRuntimeSource = (string)file_get_contents($serverRoot . '/app/Modules/Official/Task/Application/PdoTaskJobRuntime.php');
expectTaskHost(str_contains($taskRuntimeSource, 'TrustedJobPublisher'), 'official.task does not own trusted submission');
expectTaskHost(str_contains($taskRuntimeSource, 'LocalWorker'), 'official.task does not own worker execution');
$gatewaySource = (string)file_get_contents($serverRoot . '/app/common/service/export/AppFileMediaGateway.php');
expectTaskHost(!str_contains($gatewaySource, "'/public/"), 'private gateway writes below public/');

$suffix = strtolower(substr(bin2hex(random_bytes(8)), 0, 16));
$taskName = 'PB04任务' . $suffix;
$taskId = 0;
$exportPath = '';

try {
    expectTaskHost(CrontabLogic::add([
        'name' => $taskName,
        'type' => 1,
        'command' => 'crontab:demo',
        'params' => '',
        'status' => CrontabEnum::START,
        'expression' => '* * * * *',
        'sort' => 0,
        'remark' => 'PB04-TASK-OPS-HOST-001',
    ]), CrontabLogic::getError());
    $taskId = (int)Crontab::where('name', $taskName)->value('id');
    expectTaskHost($taskId > 0, 'temporary crontab was not created');

    $task = Crontab::findOrEmpty($taskId);
    expectTaskHost(!$task->isEmpty(), 'temporary crontab is missing');
    CrontabCommand::start($task->getData());
    $task = Crontab::findOrEmpty($taskId);
    expectTaskHost((string)$task->error === '', 'allowed task must succeed');
    expectTaskHost((int)$task->status === CrontabEnum::START, 'successful task must remain started');

    Db::name('crontab')->where('id', $taskId)->update(['command' => 'crontab']);
    $task = Crontab::findOrEmpty($taskId);
    CrontabCommand::start($task->getData());
    $task = Crontab::findOrEmpty($taskId);
    expectTaskHost((int)$task->status === CrontabEnum::ERROR, 'disallowed task must enter error state');
    expectTaskHost(
        str_contains((string)$task->error, '未注册或不允许调度'),
        'disallowed task must expose the stable allowlist failure'
    );

    Db::name('crontab')->where('id', $taskId)->update(['command' => 'crontab:demo']);
    expectTaskHost(CrontabLogic::operate($taskId, 'start'), CrontabLogic::getError());
    $task = Crontab::findOrEmpty($taskId);
    expectTaskHost((int)$task->status === CrontabEnum::START, 'manual retry must restore started state');
    expectTaskHost((string)$task->error === '', 'manual retry must clear the previous error');
    CrontabCommand::start($task->getData());
    $task = Crontab::findOrEmpty($taskId);
    expectTaskHost((int)$task->status === CrontabEnum::START, 'retried task must succeed');
    expectTaskHost((string)$task->error === '', 'retried task must finish without an error');

    $uri = XlsxExportService::create(
        'PB04-task-export-' . $suffix,
        ['任务', '次数', '公式文本'],
        [['crontab:demo', 2, '=1+1']]
    );
    $exportPath = $serverRoot . '/public/' . $uri;
    expectTaskHost(is_file($exportPath), 'XLSX export file was not created');
    $zip = new ZipArchive();
    expectTaskHost($zip->open($exportPath) === true, 'XLSX export is not a readable ZIP');
    $sheet = $zip->getFromName('xl/worksheets/sheet1.xml');
    expectTaskHost(is_string($sheet), 'XLSX worksheet is missing');
    expectTaskHost(str_contains($sheet, 'crontab:demo'), 'XLSX text cell is missing');
    expectTaskHost(str_contains($sheet, '<v>2</v>'), 'XLSX numeric cell is missing');
    expectTaskHost(str_contains($sheet, '=1+1'), 'formula-shaped input must remain inline text');
    $zip->close();

    $exportCallers = [
        'app/adminapi/logic/auth/AdminLogic.php',
        'app/adminapi/logic/dept/JobsLogic.php',
        'app/adminapi/logic/member/MemberLogic.php',
        'app/adminapi/logic/finance/RechargeLogic.php',
        'app/adminapi/logic/log/OperationLogLogic.php',
    ];
    foreach ($exportCallers as $relativePath) {
        $source = (string)file_get_contents($serverRoot . '/' . $relativePath);
        expectTaskHost(
            str_contains($source, 'XlsxExportService::create'),
            'export caller must use the application XLSX owner: ' . $relativePath
        );
        expectTaskHost(!str_contains($source, 'new ZipArchive'), 'duplicate XLSX writer: ' . $relativePath);
        expectTaskHost(!str_contains($source, 'function createXlsx'), 'duplicate XLSX helper: ' . $relativePath);
        expectTaskHost(!str_contains($source, 'PeanutAdmin\\TaskJob'), 'core TaskJob deep import: ' . $relativePath);
        expectTaskHost(!str_contains($source, 'PeanutAdmin\\ImportExport'), 'core ImportExport deep import: ' . $relativePath);
    }

    foreach ([
        'app/command/Crontab.php',
        'app/Modules/Official/Task/Service/CrontabLogic.php',
        'app/adminapi/logic/generator/GeneratorLogic.php',
        'app/adminapi/service/generator/GeneratorArchiveService.php',
    ] as $relativePath) {
        $source = (string)file_get_contents($serverRoot . '/' . $relativePath);
        expectTaskHost(!str_contains($source, 'PeanutAdmin\\TaskJob'), 'core TaskJob deep import: ' . $relativePath);
        expectTaskHost(!str_contains($source, 'PeanutAdmin\\ImportExport'), 'core ImportExport deep import: ' . $relativePath);
    }
} finally {
    if ($exportPath !== '' && is_file($exportPath)) {
        @unlink($exportPath);
    }
    if ($taskId > 0) {
        Db::name('crontab')->where('id', $taskId)->delete();
    }
}

expectTaskHost(!is_file($exportPath), 'temporary XLSX was not cleaned');
expectTaskHost(
    Db::name('crontab')->where('name', $taskName)->count() === 0,
    'temporary crontab was not cleaned'
);

echo "PB04-TASK-OPS-HOST-001 passed\n";
