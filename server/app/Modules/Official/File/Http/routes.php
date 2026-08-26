<?php
declare(strict_types=1);

use app\Modules\Official\File\ModuleProvider;
use app\Modules\Official\File\Http\Controller\FileController;
use app\Modules\Official\File\Http\Controller\UploadController;
use app\api\controller\UploadController as ApiUploadController;
use app\api\middleware\CheckTokenMiddleware;
use app\adminapi\http\middleware\AuthMiddleware;
use app\adminapi\http\middleware\LoginMiddleware;
use app\adminapi\http\middleware\OperationLogMiddleware;
use app\common\service\module\OfficialModuleMiddleware;
use think\facade\Route;

Route::group('api/admin', function (): void {
    Route::post('official.file.upload.image', [UploadController::class, 'image']);
    Route::post('official.file.upload.video', [UploadController::class, 'video']);
    Route::post('official.file.upload.file', [UploadController::class, 'file']);
    Route::get('official.file.list', [FileController::class, 'lists']);
    Route::post('official.file.move', [FileController::class, 'move']);
    Route::post('official.file.rename', [FileController::class, 'rename']);
    Route::post('official.file.delete', [FileController::class, 'delete']);
    Route::get('official.file.category.list', [FileController::class, 'listCate']);
    Route::post('official.file.category.add', [FileController::class, 'addCate']);
    Route::post('official.file.category.edit', [FileController::class, 'editCate']);
    Route::post('official.file.category.delete', [FileController::class, 'delCate']);
})->middleware(LoginMiddleware::class)
    ->middleware(OfficialModuleMiddleware::class, (new ModuleProvider())->moduleKey(), 'http.admin')
    ->middleware(AuthMiddleware::class)
    ->middleware(OperationLogMiddleware::class);

Route::post('api/upload/image', [ApiUploadController::class, 'image'])
    ->middleware(CheckTokenMiddleware::class)
    ->middleware(OfficialModuleMiddleware::class, (new ModuleProvider())->moduleKey(), 'http.member-upload');
