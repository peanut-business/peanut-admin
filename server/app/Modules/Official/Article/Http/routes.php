<?php
declare(strict_types=1);

use app\Modules\Official\Article\Http\ArticleModuleMiddleware;
use app\adminapi\controller\article\ArticleCateController;
use app\adminapi\controller\article\ArticleController;
use app\adminapi\http\middleware\AuthMiddleware;
use app\adminapi\http\middleware\LoginMiddleware;
use app\adminapi\http\middleware\OperationLogMiddleware;
use think\facade\Route;

Route::group('api/admin', function (): void {
    Route::get('article.articleCate/lists', [ArticleCateController::class, 'lists']);
    Route::get('article.articleCate/all', [ArticleCateController::class, 'all']);
    Route::get('article.articleCate/detail', [ArticleCateController::class, 'detail']);
    Route::post('article.articleCate/add', [ArticleCateController::class, 'add']);
    Route::post('article.articleCate/edit', [ArticleCateController::class, 'edit']);
    Route::post('article.articleCate/delete', [ArticleCateController::class, 'delete']);
    Route::post('article.articleCate/updateStatus', [ArticleCateController::class, 'updateStatus']);
    Route::get('article.article/lists', [ArticleController::class, 'lists']);
    Route::get('article.article/detail', [ArticleController::class, 'detail']);
    Route::post('article.article/add', [ArticleController::class, 'add']);
    Route::post('article.article/edit', [ArticleController::class, 'edit']);
    Route::post('article.article/delete', [ArticleController::class, 'delete']);
    Route::post('article.article/updateStatus', [ArticleController::class, 'updateStatus']);
})->middleware([
    LoginMiddleware::class,
    ArticleModuleMiddleware::class,
    AuthMiddleware::class,
    OperationLogMiddleware::class,
]);
