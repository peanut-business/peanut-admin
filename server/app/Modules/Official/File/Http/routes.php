<?php
declare(strict_types=1);

use app\Modules\Official\File\ModuleProvider;
use app\adminapi\controller\file\FileController;
use app\adminapi\controller\file\UploadController;
use app\api\controller\UploadController as ApiUploadController;
use app\api\middleware\CheckTokenMiddleware;
use app\adminapi\http\middleware\AuthMiddleware;
use app\adminapi\http\middleware\LoginMiddleware;
use app\adminapi\http\middleware\OperationLogMiddleware;
use app\common\service\module\OfficialModuleMiddleware;
use think\facade\Route;

Route::group('api/admin', function (): void {
    Route::post('upload/image', [UploadController::class, 'image']);
    Route::post('upload/video', [UploadController::class, 'video']);
    Route::post('upload/file', [UploadController::class, 'file']);
    Route::get('file/lists', [FileController::class, 'lists']);
    Route::post('file/move', [FileController::class, 'move']);
    Route::post('file/rename', [FileController::class, 'rename']);
    Route::post('file/delete', [FileController::class, 'delete']);
    Route::get('file/cate/lists', [FileController::class, 'listCate']);
    Route::post('file/cate/add', [FileController::class, 'addCate']);
    Route::post('file/cate/edit', [FileController::class, 'editCate']);
    Route::post('file/cate/delete', [FileController::class, 'delCate']);
})->middleware(LoginMiddleware::class)
    ->middleware(OfficialModuleMiddleware::class, (new ModuleProvider())->moduleKey(), 'http.admin')
    ->middleware(AuthMiddleware::class)
    ->middleware(OperationLogMiddleware::class);

Route::post('api/upload/image', [ApiUploadController::class, 'image'])
    ->middleware(CheckTokenMiddleware::class)
    ->middleware(OfficialModuleMiddleware::class, (new ModuleProvider())->moduleKey(), 'http.member-upload');
