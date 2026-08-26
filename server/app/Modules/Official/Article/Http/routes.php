<?php
declare(strict_types=1);

use app\Modules\Official\Article\Http\ArticleModuleMiddleware;
use app\Modules\Official\Article\Http\Controller\ArticleCateController;
use app\Modules\Official\Article\Http\Controller\ArticleController;
use app\adminapi\http\middleware\AuthMiddleware;
use app\adminapi\http\middleware\LoginMiddleware;
use app\adminapi\http\middleware\OperationLogMiddleware;
use think\facade\Route;

Route::group('api/admin', function (): void {
    Route::get('official.article.category.list', [ArticleCateController::class, 'lists']);
    Route::get('official.article.category.all', [ArticleCateController::class, 'all']);
    Route::get('official.article.category.detail', [ArticleCateController::class, 'detail']);
    Route::post('official.article.category.add', [ArticleCateController::class, 'add']);
    Route::post('official.article.category.edit', [ArticleCateController::class, 'edit']);
    Route::post('official.article.category.delete', [ArticleCateController::class, 'delete']);
    Route::post('official.article.category.update-status', [ArticleCateController::class, 'updateStatus']);
    Route::get('official.article.list', [ArticleController::class, 'lists']);
    Route::get('official.article.detail', [ArticleController::class, 'detail']);
    Route::post('official.article.add', [ArticleController::class, 'add']);
    Route::post('official.article.edit', [ArticleController::class, 'edit']);
    Route::post('official.article.delete', [ArticleController::class, 'delete']);
    Route::post('official.article.update-status', [ArticleController::class, 'updateStatus']);
})->middleware([
    LoginMiddleware::class,
    ArticleModuleMiddleware::class,
    AuthMiddleware::class,
    OperationLogMiddleware::class,
]);
