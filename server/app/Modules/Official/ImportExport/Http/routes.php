<?php
declare(strict_types=1);

use app\Modules\Official\ImportExport\ModuleProvider;
use app\adminapi\controller\log\OperationLogController;
use app\adminapi\http\middleware\AuthMiddleware;
use app\adminapi\http\middleware\LoginMiddleware;
use app\adminapi\http\middleware\OperationLogMiddleware;
use app\common\service\module\OfficialModuleMiddleware;
use think\facade\Route;

Route::group('api/admin', function (): void {
    Route::post('log/export', [OperationLogController::class, 'export']);
    Route::get('log/export/status', [OperationLogController::class, 'exportStatus']);
    Route::get('log/export/download', [OperationLogController::class, 'exportDownload']);
})->middleware(LoginMiddleware::class)
    ->middleware(OfficialModuleMiddleware::class, (new ModuleProvider())->moduleKey(), 'http.admin')
    ->middleware(AuthMiddleware::class)
    ->middleware(OperationLogMiddleware::class);
