<?php
declare(strict_types=1);

use app\api\controller\IndexController as ApiIndexController;
use app\api\controller\LoginController as ApiLoginController;
use app\api\controller\ArticleController as ApiArticleController;
use app\api\controller\SearchController as ApiSearchController;
use app\api\controller\StorageController as ApiStorageController;
use app\api\controller\PcController as ApiPcController;
use app\api\controller\DecorationController as ApiDecorationController;
use app\api\middleware\CheckTokenMiddleware;
use app\api\middleware\PublicArticleTenantMiddleware;
use app\api\middleware\PublicDecorationTenantMiddleware;
use app\api\middleware\PublicHotSearchTenantMiddleware;
use think\facade\Route;

// ═══════════════════════════════════════════════════════════════════════════════
// 用户端 API（/api/user/ 和 /api/  命名空间）
// 公开接口无中间件；需登录接口挂 CheckTokenMiddleware
// ═══════════════════════════════════════════════════════════════════════════════

// ─── 公开接口（无需 token） ────────────────────────────────────────────────────
Route::get('api/index/index',   [ApiIndexController::class, 'index'])
    ->middleware(PublicArticleTenantMiddleware::class, 'article.index');
Route::get('api/index/config',  [ApiIndexController::class, 'config'])
    ->middleware(PublicDecorationTenantMiddleware::class, 'decoration.config');
Route::get('api/index/policy',  [ApiIndexController::class, 'policy'])
    ->middleware(PublicDecorationTenantMiddleware::class, 'decoration.config');

Route::post('api/login/logout',   [ApiLoginController::class, 'logout']);
Route::get('api/storage/delivery', [ApiStorageController::class, 'delivery']);

Route::get('api/article/cate',    [ApiArticleController::class, 'cate'])
    ->middleware(PublicArticleTenantMiddleware::class, 'article.cate');
Route::get('api/article/lists',   [ApiArticleController::class, 'lists'])
    ->middleware(PublicArticleTenantMiddleware::class, 'article.lists');
Route::get('api/article/detail',  [ApiArticleController::class, 'detail'])
    ->middleware(PublicArticleTenantMiddleware::class, 'article.detail');

Route::get('api/search/hotLists', [ApiSearchController::class, 'hotLists'])
    ->middleware(PublicHotSearchTenantMiddleware::class);

// 装修消费（匿名只读，保存后立即生效）
Route::get('api/decoration/mobile', [ApiDecorationController::class, 'mobilePage'])
    ->middleware(PublicDecorationTenantMiddleware::class, 'decoration.mobile-page');
Route::get('api/decoration/tabbar', [ApiDecorationController::class, 'tabbar'])
    ->middleware(PublicDecorationTenantMiddleware::class, 'decoration.config');
Route::get('api/decoration/pc', [ApiDecorationController::class, 'pcPage'])
    ->middleware(PublicDecorationTenantMiddleware::class, 'decoration.pc-page');

// PC 端聚合（公开）
Route::get('api/pc/config',         [ApiPcController::class, 'config'])
    ->middleware(PublicDecorationTenantMiddleware::class, 'decoration.config');
Route::get('api/pc/index',          [ApiPcController::class, 'index'])
    ->middleware(PublicArticleTenantMiddleware::class, 'article.pc-index');
Route::get('api/pc/infoCenter',     [ApiPcController::class, 'infoCenter'])
    ->middleware(PublicArticleTenantMiddleware::class, 'article.info-center');
Route::get('api/pc/articleDetail',  [ApiPcController::class, 'articleDetail'])
    ->middleware(PublicArticleTenantMiddleware::class, 'article.pc-detail');

// ─── 需登录接口（挂 CheckTokenMiddleware） ──────────────────────────────────
Route::group('api', function () {
    // 文章收藏
    Route::post('article/addCollect',    [ApiArticleController::class, 'addCollect']);
    Route::post('article/cancelCollect', [ApiArticleController::class, 'cancelCollect']);
    Route::get('article/collect',        [ApiArticleController::class, 'collect']);

})->middleware([CheckTokenMiddleware::class]);
