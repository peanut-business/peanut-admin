<?php
declare(strict_types=1);

use app\adminapi\controller\auth\AdminController;
use app\adminapi\controller\auth\LoginController;
use app\adminapi\controller\auth\MenuController;
use app\adminapi\controller\auth\RoleController;
use app\adminapi\controller\config\ConfigController;
use app\adminapi\controller\dept\DeptController;
use app\adminapi\controller\dept\JobsController;
use app\adminapi\controller\dict\DictTypeController;
use app\adminapi\controller\dict\DictDataController;
use app\adminapi\controller\file\FileController;
use app\adminapi\controller\file\UploadController;
use app\adminapi\controller\log\OperationLogController;
use app\adminapi\http\middleware\AuthMiddleware;
use app\adminapi\http\middleware\LoginMiddleware;
use app\adminapi\http\middleware\OperationLogMiddleware;
use think\facade\Route;

// ─── 免登录路由（不挂任何鉴权中间件） ──────────────────────────────────────
Route::post('api/user/login',  [LoginController::class, 'login']);
Route::post('api/user/logout', [LoginController::class, 'logout']);
Route::post('admin/login/login',  [LoginController::class, 'login']);
Route::post('admin/login/logout', [LoginController::class, 'logout']);

// ─── Arco Design Pro Vue 兼容路由（仅需登录，不做 RBAC） ─────────────────────
Route::group(function () {
    Route::post('api/user/info', [LoginController::class, 'info']);
    Route::post('api/user/menu', [MenuController::class, 'route']);
})->middleware(LoginMiddleware::class);

// ─── 管理后台完整 API（Login → Auth 两层中间件） ─────────────────────────────
// 前缀统一挂在 api/ 下，前端 vite 代理只转发 /api，生产 nginx 也只需转一条前缀。
Route::group('api/admin', function () {
    Route::get('login/info', [LoginController::class, 'info']);

    // 菜单
    Route::get('menu/route',   [MenuController::class, 'route']);
    Route::get('menu/lists',   [MenuController::class, 'lists']);
    Route::get('menu/all',     [MenuController::class, 'all']);
    Route::get('menu/detail',  [MenuController::class, 'detail']);
    Route::post('menu/add',    [MenuController::class, 'add']);
    Route::post('menu/edit',   [MenuController::class, 'edit']);
    Route::post('menu/delete', [MenuController::class, 'delete']);
    Route::post('menu/status', [MenuController::class, 'updateStatus']);

    // 角色
    Route::get('role/lists',   [RoleController::class, 'lists']);
    Route::get('role/all',     [RoleController::class, 'all']);
    Route::get('role/detail',  [RoleController::class, 'detail']);
    Route::post('role/add',    [RoleController::class, 'add']);
    Route::post('role/edit',   [RoleController::class, 'edit']);
    Route::post('role/delete', [RoleController::class, 'delete']);

    // 管理员
    Route::get('admin/lists',   [AdminController::class, 'lists']);
    Route::get('admin/detail',  [AdminController::class, 'detail']);
    Route::get('admin/self',    [AdminController::class, 'self']);
    Route::post('admin/add',    [AdminController::class, 'add']);
    Route::post('admin/edit',   [AdminController::class, 'edit']);
    Route::post('admin/delete', [AdminController::class, 'delete']);
    Route::post('admin/status', [AdminController::class, 'updateStatus']);

    // 部门
    Route::get('dept/lists',   [DeptController::class, 'lists']);
    Route::get('dept/all',     [DeptController::class, 'all']);
    Route::get('dept/detail',  [DeptController::class, 'detail']);
    Route::post('dept/add',    [DeptController::class, 'add']);
    Route::post('dept/edit',   [DeptController::class, 'edit']);
    Route::post('dept/delete', [DeptController::class, 'delete']);
    Route::post('dept/status', [DeptController::class, 'updateStatus']);

    // 岗位
    Route::get('jobs/lists',   [JobsController::class, 'lists']);
    Route::get('jobs/all',     [JobsController::class, 'all']);
    Route::get('jobs/detail',  [JobsController::class, 'detail']);
    Route::post('jobs/add',    [JobsController::class, 'add']);
    Route::post('jobs/edit',   [JobsController::class, 'edit']);
    Route::post('jobs/delete', [JobsController::class, 'delete']);
    Route::post('jobs/status', [JobsController::class, 'updateStatus']);

    // 字典类型
    Route::get('dict/type/lists',   [DictTypeController::class, 'lists']);
    Route::get('dict/type/all',     [DictTypeController::class, 'all']);
    Route::get('dict/type/detail',  [DictTypeController::class, 'detail']);
    Route::post('dict/type/add',    [DictTypeController::class, 'add']);
    Route::post('dict/type/edit',   [DictTypeController::class, 'edit']);
    Route::post('dict/type/delete', [DictTypeController::class, 'delete']);
    Route::post('dict/type/status', [DictTypeController::class, 'updateStatus']);

    // 字典数据
    Route::get('dict/data/lists',   [DictDataController::class, 'lists']);
    Route::get('dict/data/byType',  [DictDataController::class, 'byType']);
    Route::get('dict/data/detail',  [DictDataController::class, 'detail']);
    Route::post('dict/data/add',    [DictDataController::class, 'add']);
    Route::post('dict/data/edit',   [DictDataController::class, 'edit']);
    Route::post('dict/data/delete', [DictDataController::class, 'delete']);
    Route::post('dict/data/status', [DictDataController::class, 'updateStatus']);

    // 素材库 - 上传
    Route::post('upload/image', [UploadController::class, 'image']);
    Route::post('upload/video', [UploadController::class, 'video']);
    Route::post('upload/file',  [UploadController::class, 'file']);

    // 素材库 - 文件
    Route::get('file/lists',   [FileController::class, 'lists']);
    Route::post('file/move',   [FileController::class, 'move']);
    Route::post('file/rename', [FileController::class, 'rename']);
    Route::post('file/delete', [FileController::class, 'delete']);

    // 素材库 - 分类
    Route::get('file/cate/lists',   [FileController::class, 'listCate']);
    Route::post('file/cate/add',    [FileController::class, 'addCate']);
    Route::post('file/cate/edit',   [FileController::class, 'editCate']);
    Route::post('file/cate/delete', [FileController::class, 'delCate']);

    // 操作日志
    Route::get('log/lists',  [OperationLogController::class, 'lists']);
    Route::post('log/clear', [OperationLogController::class, 'clear']);

    // 系统配置 - 网站设置
    Route::get('config/website',      [ConfigController::class, 'getWebsite']);
    Route::post('config/website/save', [ConfigController::class, 'saveWebsite']);
})->middleware([LoginMiddleware::class, AuthMiddleware::class, OperationLogMiddleware::class]);
