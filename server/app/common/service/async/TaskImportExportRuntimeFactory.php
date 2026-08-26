<?php
declare(strict_types=1);

namespace app\common\service\async;

use app\Modules\Official\ImportExport\Application\TaskImportExportRuntime;
use app\Modules\Official\Task\ModuleProvider as TaskModuleProvider;
use PDO;

/** Host assembly point for the official.task and official.import-export contracts. */
final class TaskImportExportRuntimeFactory
{
    public static function fromConfig(PDO $pdo): TaskImportExportRuntime
    {
        $signingKey = (string)config('async.signing_key', '');
        return new TaskImportExportRuntime(
            $pdo,
            (new TaskModuleProvider())->jobs($pdo, $signingKey),
        );
    }
}
