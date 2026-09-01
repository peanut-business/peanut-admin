<?php
declare(strict_types=1);

use app\Modules\Official\Task\ModuleProvider;
use app\Modules\Official\Task\Http\Controller\CrontabController;
use app\adminapi\http\middleware\AuthMiddleware;
use app\adminapi\http\middleware\LoginMiddleware;
use app\adminapi\http\middleware\OperationLogMiddleware;
use app\common\service\module\OfficialModuleMiddleware;
use think\facade\Route;

if (($peanutRouteApplication ?? null) !== 'adminapi') {
    return;
}

Route::group(function (): void {
    Route::get('official.task.list', [CrontabController::class, 'lists']);
    Route::get('official.task.detail', [CrontabController::class, 'detail']);
    Route::get('official.task.expression', [CrontabController::class, 'expression']);
    Route::post('official.task.add', [CrontabController::class, 'add']);
    Route::post('official.task.edit', [CrontabController::class, 'edit']);
    Route::post('official.task.delete', [CrontabController::class, 'delete']);
    Route::post('official.task.operate', [CrontabController::class, 'operate']);
})->middleware(LoginMiddleware::class)
    ->middleware(OfficialModuleMiddleware::class, (new ModuleProvider())->moduleKey(), 'http.admin')
    ->middleware(AuthMiddleware::class)
    ->middleware(OperationLogMiddleware::class);
