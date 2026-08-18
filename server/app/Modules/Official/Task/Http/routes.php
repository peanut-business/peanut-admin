<?php
declare(strict_types=1);

use app\Modules\Official\Task\ModuleProvider;
use app\adminapi\controller\crontab\CrontabController;
use app\adminapi\http\middleware\AuthMiddleware;
use app\adminapi\http\middleware\LoginMiddleware;
use app\adminapi\http\middleware\OperationLogMiddleware;
use app\common\service\module\OfficialModuleMiddleware;
use think\facade\Route;

Route::group('api/admin', function (): void {
    Route::get('crontab/lists', [CrontabController::class, 'lists']);
    Route::get('crontab/detail', [CrontabController::class, 'detail']);
    Route::get('crontab/expression', [CrontabController::class, 'expression']);
    Route::post('crontab/add', [CrontabController::class, 'add']);
    Route::post('crontab/edit', [CrontabController::class, 'edit']);
    Route::post('crontab/delete', [CrontabController::class, 'delete']);
    Route::post('crontab/operate', [CrontabController::class, 'operate']);
})->middleware(LoginMiddleware::class)
    ->middleware(OfficialModuleMiddleware::class, (new ModuleProvider())->moduleKey(), 'http.admin')
    ->middleware(AuthMiddleware::class)
    ->middleware(OperationLogMiddleware::class);
