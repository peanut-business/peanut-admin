<?php
declare(strict_types=1);

use app\adminapi\logic\crontab\CrontabLogic;
use app\command\Crontab as CrontabCommand;
use app\common\enum\CrontabEnum;
use app\common\model\Crontab;
use app\common\service\XlsxExportService;
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
        'app/adminapi/logic/crontab/CrontabLogic.php',
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
