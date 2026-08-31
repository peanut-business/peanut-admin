<?php
declare(strict_types=1);

namespace app\common\service\async;

use app\Modules\Official\ImportExport\Application\TaskImportExportRuntime;
use app\Modules\Official\Task\ModuleProvider as TaskModuleProvider;
use app\Modules\Official\Task\Contracts\TaskJobRuntime;
use app\Modules\Official\Task\Contracts\TaskScheduler;
use PDO;

/** Host assembly point for the official.task and official.import-export contracts. */
final class TaskImportExportRuntimeFactory
{
    public static function fromConfig(PDO $pdo): TaskImportExportRuntime
    {
        return new TaskImportExportRuntime(
            $pdo,
            self::tasks($pdo),
        );
    }

    public static function scheduler(PDO $pdo): TaskScheduler
    {
        $tasks = self::tasks($pdo);
        $imports = new TaskImportExportRuntime($pdo, $tasks);
        return (new TaskModuleProvider())->scheduler($tasks, $imports->workerDefinition());
    }

    private static function tasks(PDO $pdo): TaskJobRuntime
    {
        return (new TaskModuleProvider())->jobs($pdo, (string)config('async.signing_key', ''));
    }
}
