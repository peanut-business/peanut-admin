<?php
declare(strict_types=1);

use app\Modules\Official\ImportExport\ModuleProvider;
use app\Modules\Official\ImportExport\Http\Controller\OperationLogExportController;
use app\adminapi\http\middleware\AuthMiddleware;
use app\adminapi\http\middleware\LoginMiddleware;
use app\adminapi\http\middleware\OperationLogMiddleware;
use app\common\service\module\OfficialModuleMiddleware;
use think\facade\Route;

Route::group('api/admin', function (): void {
    Route::post('official.import-export.operation-log.export', [OperationLogExportController::class, 'export']);
    Route::get('official.import-export.operation.status', [OperationLogExportController::class, 'exportStatus']);
    Route::get('official.import-export.result.download', [OperationLogExportController::class, 'exportDownload']);
})->middleware(LoginMiddleware::class)
    ->middleware(OfficialModuleMiddleware::class, (new ModuleProvider())->moduleKey(), 'http.admin')
    ->middleware(AuthMiddleware::class)
    ->middleware(OperationLogMiddleware::class);
