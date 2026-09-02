<?php
declare(strict_types=1);

use app\Modules\Fixture\DeliveryRecord\Http\DeliveryRecordController;
use app\Modules\Fixture\DeliveryRecord\ModuleProvider;
use app\adminapi\http\middleware\LoginMiddleware;
use app\adminapi\http\middleware\OperationLogMiddleware;
use app\common\service\module\OfficialModuleMiddleware;
use think\facade\Route;

if (($peanutRouteApplication ?? null) !== 'adminapi') {
    return;
}

// Module commands own permission checks; the generic root-bypass RBAC middleware is not used.
Route::get('fixtures/delivery-records', [DeliveryRecordController::class, 'lists'])
    ->middleware(LoginMiddleware::class)
    ->middleware(OfficialModuleMiddleware::class, (new ModuleProvider())->moduleKey(), 'http.admin');
Route::post('fixtures/delivery-records', [DeliveryRecordController::class, 'record'])
    ->middleware(LoginMiddleware::class)
    ->middleware(OfficialModuleMiddleware::class, (new ModuleProvider())->moduleKey(), 'http.admin')
    ->middleware(OperationLogMiddleware::class);
