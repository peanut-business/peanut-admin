<?php
declare(strict_types=1);

use app\adminapi\http\middleware\AuthMiddleware;
use app\adminapi\http\middleware\InitMiddleware;
use app\adminapi\http\middleware\LoginMiddleware;
use think\facade\Route;

// Arco Design Pro Vue 兼容路由（前端默认调用的路径）
Route::post('api/user/login',  'adminapi.auth.Login/login');
Route::post('api/user/logout', 'adminapi.auth.Login/logout');
Route::post('api/user/info',   'adminapi.auth.Login/info')
    ->middleware([InitMiddleware::class, LoginMiddleware::class]);
Route::post('api/user/menu',   'adminapi.auth.Menu/route')
    ->middleware([InitMiddleware::class, LoginMiddleware::class]);

// 管理后台完整 API
Route::group('admin', function () {
    Route::post('login/login',  'auth.Login/login');
    Route::post('login/logout', 'auth.Login/logout');

    Route::group('', function () {
        Route::get('login/info',   'auth.Login/info');
        Route::get('menu/route',   'auth.Menu/route');
        Route::get('menu/lists',   'auth.Menu/lists');
        Route::get('menu/all',     'auth.Menu/all');
        Route::get('menu/detail',  'auth.Menu/detail');
        Route::post('menu/add',    'auth.Menu/add');
        Route::post('menu/edit',   'auth.Menu/edit');
        Route::post('menu/delete', 'auth.Menu/delete');
        Route::post('menu/status', 'auth.Menu/updateStatus');

        Route::get('role/lists',   'auth.Role/lists');
        Route::get('role/all',     'auth.Role/all');
        Route::get('role/detail',  'auth.Role/detail');
        Route::post('role/add',    'auth.Role/add');
        Route::post('role/edit',   'auth.Role/edit');
        Route::post('role/delete', 'auth.Role/delete');

        Route::get('admin/lists',   'auth.Admin/lists');
        Route::get('admin/detail',  'auth.Admin/detail');
        Route::get('admin/self',    'auth.Admin/self');
        Route::post('admin/add',    'auth.Admin/add');
        Route::post('admin/edit',   'auth.Admin/edit');
        Route::post('admin/delete', 'auth.Admin/delete');
        Route::post('admin/status', 'auth.Admin/updateStatus');
    });
})->middleware([InitMiddleware::class, LoginMiddleware::class, AuthMiddleware::class]);
