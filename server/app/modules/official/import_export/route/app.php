<?php
declare(strict_types=1);

use app\modules\official\import_export\controller\OperationLogExportController;
use app\modules\official\import_export\controller\configurationTransferController;
use app\adminapi\http\middleware\AuthMiddleware;
use app\adminapi\http\middleware\LoginMiddleware;
use app\adminapi\http\middleware\OperationLogMiddleware;
use app\common\infrastructure\module\OfficialModuleMiddleware;
use think\facade\Route;

if (($peanutRouteApplication ?? null) !== 'adminapi') {
    return;
}

Route::group(function (): void {
    Route::post('official.import-export.operation-log.export', [OperationLogExportController::class, 'export']);
    Route::get('official.import-export.operation.status', [OperationLogExportController::class, 'exportStatus']);
    Route::get('official.import-export.result.download', [OperationLogExportController::class, 'exportDownload']);
    Route::get('official.import-export.configuration.export', [ConfigurationTransferController::class, 'export']);
    Route::post('official.import-export.configuration.dry-run', [ConfigurationTransferController::class, 'dryRun']);
    Route::post('official.import-export.configuration.apply', [ConfigurationTransferController::class, 'apply']);
})->middleware(LoginMiddleware::class)
    ->middleware(OfficialModuleMiddleware::class, 'official.import-export', 'http.admin')
    ->middleware(AuthMiddleware::class)
    ->middleware(OperationLogMiddleware::class);
