<?php
declare(strict_types=1);

use app\Modules\Fixture\DeliveryRecord\Http\DeliveryRecordController;
use app\adminapi\http\middleware\LoginMiddleware;
use app\adminapi\http\middleware\OperationLogMiddleware;
use think\facade\Route;

// Module commands own permission checks; the generic root-bypass RBAC middleware is not used.
Route::get('api/admin/fixtures/delivery-records', [DeliveryRecordController::class, 'lists'])
    ->middleware(LoginMiddleware::class);
Route::post('api/admin/fixtures/delivery-records', [DeliveryRecordController::class, 'record'])
    ->middleware([LoginMiddleware::class, OperationLogMiddleware::class]);
