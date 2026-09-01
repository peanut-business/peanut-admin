<?php
declare(strict_types=1);

use app\Modules\Official\Task\Application\CrontabApplicationService;
use app\command\Crontab as CrontabCommand;
use app\common\enum\CrontabEnum;
use app\common\execution\ExecutionContextStore;
use app\Modules\Official\Task\Model\Crontab;
use app\common\service\XlsxExportService;
use app\common\service\storage\StorageService;
use app\Modules\Official\ImportExport\Application\TaskImportExportRuntime;
use app\common\service\async\TaskImportExportRuntimeFactory;
use app\Modules\Official\ImportExport\Infrastructure\File\AppFileMediaGateway;
use app\common\service\export\OperationLogExportProvider;
use app\command\TenantTaskWorker;
use PeanutAdmin\ImportExport\Application\ImportExportService;
use PeanutAdmin\Kernel\Auth\TenantContext;
use PeanutAdmin\Kernel\Auth\ValidatedTenantSession;
use PeanutAdmin\Kernel\Tenancy\TenantScope;
use PeanutAdmin\TaskJob\Submission\TrustedJobPublisher;
use think\facade\Db;

require dirname(__DIR__, 2) . '/bootstrap/environment.php';
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
$tenantId = (int)Db::name('tenant')->where('status', 'active')->order('id')->value('id');
expectTaskHost($tenantId > 0, 'active Tenant fixture is unavailable');
$context = TenantContext::fromValidatedSession(new ValidatedTenantSession(
    1,
    'PB04-TASK-IMPORT-EXPORT-HOST',
    $tenantId,
    1,
    1,
    'admin-web',
    new DateTimeImmutable('2031-01-01T00:00:00Z'),
    1,
), 'pb04-task-import-export-host');
$scope = TenantScope::fromTrustedContext($tenantId, 'pb04-task-import-export-host');

expectTaskHost(class_exists(TaskImportExportRuntime::class), 'application async Runtime is missing');
expectTaskHost(class_exists(TaskImportExportRuntimeFactory::class), 'application async Runtime Host assembly is missing');
expectTaskHost(class_exists(TenantTaskWorker::class), 'Tenant worker command is missing');
expectTaskHost(is_subclass_of(OperationLogExportProvider::class, \PeanutAdmin\ImportExport\Contract\DataProvider::class), 'operation-log export provider does not implement the Core contract');
expectTaskHost(is_subclass_of(AppFileMediaGateway::class, \PeanutAdmin\ImportExport\File\FileMediaGateway::class), 'private file gateway does not implement the Core contract');
expectTaskHost(class_exists(TrustedJobPublisher::class) && class_exists(ImportExportService::class), 'locked Core async contracts are unavailable');

$migrationSource = (string)file_get_contents($serverRoot . '/database/init.sql');
expectTaskHost(str_contains($migrationSource, 'pa_task_job'), 'Task/Job schema is not owned by the application migration');
expectTaskHost(str_contains($migrationSource, 'pa_import_export_operation'), 'Import/Export schema is not owned by the application migration');
expectTaskHost(str_contains($migrationSource, 'pa_file_object'), 'private file metadata schema is not owned by the application migration');
expectTaskHost(!str_contains($migrationSource, 'public/storage'), 'async migration refers to public storage');

$runtimeSource = (string)file_get_contents($serverRoot . '/app/Modules/Official/ImportExport/Application/TaskImportExportRuntime.php');
expectTaskHost(str_contains($runtimeSource, 'TaskJobRuntime'), 'Import/Export does not depend on the official Task Runtime contract');
expectTaskHost(!str_contains($runtimeSource, 'PdoTaskJobRepository'), 'Import/Export bypasses the official Task Runtime repository boundary');
expectTaskHost(!str_contains($runtimeSource, 'TrustedJobPublisher'), 'Import/Export bypasses the official Task Runtime publisher boundary');
$runtimeFactorySource = (string)file_get_contents($serverRoot . '/app/common/service/async/TaskImportExportRuntimeFactory.php');
expectTaskHost(str_contains($runtimeFactorySource, 'TaskModuleProvider'), 'Import/Export Host assembly does not use the official Task Module provider');
$taskRuntimeSource = (string)file_get_contents($serverRoot . '/app/Modules/Official/Task/Application/PdoTaskJobRuntime.php');
expectTaskHost(str_contains($taskRuntimeSource, 'TrustedJobPublisher'), 'official.task does not own trusted submission');
expectTaskHost(str_contains($taskRuntimeSource, 'LocalWorker'), 'official.task does not own worker execution');
$gatewaySource = (string)file_get_contents($serverRoot . '/app/Modules/Official/ImportExport/Infrastructure/File/AppFileMediaGateway.php');
expectTaskHost(!str_contains($gatewaySource, "'/public/"), 'private gateway writes below public/');

$suffix = strtolower(substr(bin2hex(random_bytes(8)), 0, 16));
$taskName = 'PB04任务' . $suffix;
$taskId = 0;
$exportPath = '';
$exportFileKey = '';

try {
    expectTaskHost(
        app(ExecutionContextStore::class)->run(
            new \app\common\execution\AdminExecutionContext($context, 'test.task-import-export.crontab.add'),
            fn() => app(CrontabApplicationService::class)->add([
                'name' => $taskName,
                'type' => 1,
                'command' => 'crontab:demo',
                'params' => '',
                'status' => CrontabEnum::START,
                'expression' => '* * * * *',
                'sort' => 0,
                'remark' => 'PB04-TASK-OPS-HOST-001',
            ]),
        ),
        app(CrontabApplicationService::class)->getError(),
    );
    $taskId = (int)app(ExecutionContextStore::class)->run(
        new \app\common\execution\AdminExecutionContext($context, 'test.task-import-export.crontab.query'),
        fn() => Crontab::where('name', $taskName)->value('id'),
    );
    expectTaskHost($taskId > 0, 'temporary crontab was not created');

    $task = app(ExecutionContextStore::class)->run(
        new \app\common\execution\AdminExecutionContext($context, 'test.task-import-export.crontab.find'),
        fn() => Crontab::findOrEmpty($taskId),
    );
    expectTaskHost(!$task->isEmpty(), 'temporary crontab is missing');
    CrontabCommand::start($scope, $task->getData());
    $task = app(ExecutionContextStore::class)->run(
        new \app\common\execution\AdminExecutionContext($context, 'test.task-import-export.crontab.find.allowed'),
        fn() => Crontab::findOrEmpty($taskId),
    );
    expectTaskHost((string)$task->error === '', 'allowed task must succeed');
    expectTaskHost((int)$task->status === CrontabEnum::START, 'successful task must remain started');

    app(ExecutionContextStore::class)->run(
        new \app\common\execution\AdminExecutionContext($context, 'test.task-import-export.crontab.seed-denied'),
        fn() => Db::name('crontab')->where('id', $taskId)->update(['command' => 'crontab']),
    );
    $task = app(ExecutionContextStore::class)->run(
        new \app\common\execution\AdminExecutionContext($context, 'test.task-import-export.crontab.find.denied'),
        fn() => Crontab::findOrEmpty($taskId),
    );
    CrontabCommand::start($scope, $task->getData());
    $task = app(ExecutionContextStore::class)->run(
        new \app\common\execution\AdminExecutionContext($context, 'test.task-import-export.crontab.find.denied-result'),
        fn() => Crontab::findOrEmpty($taskId),
    );
    expectTaskHost((int)$task->status === CrontabEnum::ERROR, 'disallowed task must enter error state');
    expectTaskHost(
        str_contains((string)$task->error, '未注册或不允许调度'),
        'disallowed task must expose the stable allowlist failure'
    );

    app(ExecutionContextStore::class)->run(
        new \app\common\execution\AdminExecutionContext($context, 'test.task-import-export.crontab.seed-retry'),
        fn() => Db::name('crontab')->where('id', $taskId)->update(['command' => 'crontab:demo']),
    );
    expectTaskHost(
        app(ExecutionContextStore::class)->run(
            new \app\common\execution\AdminExecutionContext($context, 'test.task-import-export.crontab.operate'),
            fn() => app(CrontabApplicationService::class)->operate($taskId, 'start'),
        ),
        app(CrontabApplicationService::class)->getError(),
    );
    $task = app(ExecutionContextStore::class)->run(
        new \app\common\execution\AdminExecutionContext($context, 'test.task-import-export.crontab.find.retry'),
        fn() => Crontab::findOrEmpty($taskId),
    );
    expectTaskHost((int)$task->status === CrontabEnum::START, 'manual retry must restore started state');
    expectTaskHost((string)$task->error === '', 'manual retry must clear the previous error');
    CrontabCommand::start($scope, $task->getData());
    $task = app(ExecutionContextStore::class)->run(
        new \app\common\execution\AdminExecutionContext($context, 'test.task-import-export.crontab.find.retried'),
        fn() => Crontab::findOrEmpty($taskId),
    );
    expectTaskHost((int)$task->status === CrontabEnum::START, 'retried task must succeed');
    expectTaskHost((string)$task->error === '', 'retried task must finish without an error');

    $file = app(ExecutionContextStore::class)->run(
        new \app\common\execution\AdminExecutionContext($context, 'test.task-import-export.xlsx.export'),
        fn() => XlsxExportService::createForTenant(
            $context,
            'PB04-task-export-' . $suffix,
            ['任务', '次数', '公式文本'],
            [['crontab:demo', 2, '=1+1']],
        ),
    );
    $exportFileKey = (string)($file['file_key'] ?? '');
    $exportPath = $serverRoot . '/private/storage/' . (string)($file['object_key'] ?? '');
    expectTaskHost($exportFileKey !== '', 'XLSX export file key is missing');
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
        'app/adminapi/application/auth/AdminApplicationService.php',
        'app/adminapi/application/dept/JobsApplicationService.php',
        'app/Modules/Official/Member/Application/MemberAdministrationService.php',
        'app/Modules/Official/Payment/Application/RechargeAdministrationService.php',
        'app/adminapi/application/log/OperationLogApplicationService.php',
    ];
    foreach ($exportCallers as $relativePath) {
        $source = (string)file_get_contents($serverRoot . '/' . $relativePath);
        expectTaskHost(
            str_contains($source, 'XlsxExportService::createForTenant'),
            'export caller must use the application XLSX owner: ' . $relativePath
        );
        expectTaskHost(
            preg_match('/XlsxExportService::create\s*\(/', $source) !== 1,
            'export caller retained the instance-wide XLSX API: ' . $relativePath
        );
        expectTaskHost(!str_contains($source, 'new ZipArchive'), 'duplicate XLSX writer: ' . $relativePath);
        expectTaskHost(!str_contains($source, 'function createXlsx'), 'duplicate XLSX helper: ' . $relativePath);
        expectTaskHost(!str_contains($source, 'PeanutAdmin\\TaskJob'), 'core TaskJob deep import: ' . $relativePath);
        expectTaskHost(!str_contains($source, 'PeanutAdmin\\ImportExport'), 'core ImportExport deep import: ' . $relativePath);
    }

    foreach ([
        'app/command/Crontab.php',
        'app/Modules/Official/Task/Application/CrontabApplicationService.php',
        'app/adminapi/application/generator/GeneratorApplicationService.php',
        'app/adminapi/service/generator/GeneratorArchiveService.php',
    ] as $relativePath) {
        $source = (string)file_get_contents($serverRoot . '/' . $relativePath);
        expectTaskHost(!str_contains($source, 'PeanutAdmin\\TaskJob'), 'core TaskJob deep import: ' . $relativePath);
        expectTaskHost(!str_contains($source, 'PeanutAdmin\\ImportExport'), 'core ImportExport deep import: ' . $relativePath);
    }
} finally {
    if ($exportFileKey !== '') {
        StorageService::fromDefaultConnection()->delete($tenantId, $exportFileKey);
    }
    if ($taskId > 0) {
        app(ExecutionContextStore::class)->run(
            new \app\common\execution\AdminExecutionContext($context, 'test.task-import-export.crontab.cleanup'),
            fn() => Db::name('crontab')->where('id', $taskId)->delete(),
        );
    }
}

expectTaskHost(!is_file($exportPath), 'temporary XLSX was not cleaned');
expectTaskHost(
    app(ExecutionContextStore::class)->run(
        new \app\common\execution\AdminExecutionContext($context, 'test.task-import-export.crontab.cleanup.verify'),
        fn() => Db::name('crontab')->where('name', $taskName)->count(),
    ) === 0,
    'temporary crontab was not cleaned'
);

echo "PB04-TASK-OPS-HOST-001 passed\n";
