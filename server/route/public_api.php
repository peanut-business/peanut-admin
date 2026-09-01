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

if (($peanutRouteApplication ?? null) !== 'api') {
    return;
}

// ═══════════════════════════════════════════════════════════════════════════════
// 用户端 API（/api/user/ 和 /api/  命名空间）
// 公开接口无中间件；需登录接口挂 CheckTokenMiddleware
// ═══════════════════════════════════════════════════════════════════════════════

// ─── 公开接口（无需 token） ────────────────────────────────────────────────────
Route::get('index/index',   [ApiIndexController::class, 'index'])
    ->middleware(PublicArticleTenantMiddleware::class, 'article.index');
Route::get('index/config',  [ApiIndexController::class, 'config'])
    ->middleware(PublicDecorationTenantMiddleware::class, 'decoration.config');
Route::get('index/policy',  [ApiIndexController::class, 'policy'])
    ->middleware(PublicDecorationTenantMiddleware::class, 'decoration.config');

Route::post('login/logout',   [ApiLoginController::class, 'logout']);
Route::get('storage/delivery', [ApiStorageController::class, 'delivery']);

Route::get('article/cate',    [ApiArticleController::class, 'cate'])
    ->middleware(PublicArticleTenantMiddleware::class, 'article.cate');
Route::get('article/lists',   [ApiArticleController::class, 'lists'])
    ->middleware(PublicArticleTenantMiddleware::class, 'article.lists');
Route::get('article/detail',  [ApiArticleController::class, 'detail'])
    ->middleware(PublicArticleTenantMiddleware::class, 'article.detail');

Route::get('search/hotLists', [ApiSearchController::class, 'hotLists'])
    ->middleware(PublicHotSearchTenantMiddleware::class);

// 装修消费（匿名只读，保存后立即生效）
Route::get('decoration/mobile', [ApiDecorationController::class, 'mobilePage'])
    ->middleware(PublicDecorationTenantMiddleware::class, 'decoration.mobile-page');
Route::get('decoration/tabbar', [ApiDecorationController::class, 'tabbar'])
    ->middleware(PublicDecorationTenantMiddleware::class, 'decoration.config');
Route::get('decoration/pc', [ApiDecorationController::class, 'pcPage'])
    ->middleware(PublicDecorationTenantMiddleware::class, 'decoration.pc-page');

// PC 端聚合（公开）
Route::get('pc/config',         [ApiPcController::class, 'config'])
    ->middleware(PublicDecorationTenantMiddleware::class, 'decoration.config');
Route::get('pc/index',          [ApiPcController::class, 'index'])
    ->middleware(PublicArticleTenantMiddleware::class, 'article.pc-index');
Route::get('pc/infoCenter',     [ApiPcController::class, 'infoCenter'])
    ->middleware(PublicArticleTenantMiddleware::class, 'article.info-center');
Route::get('pc/articleDetail',  [ApiPcController::class, 'articleDetail'])
    ->middleware(PublicArticleTenantMiddleware::class, 'article.pc-detail');

// ─── 需登录接口（挂 CheckTokenMiddleware） ──────────────────────────────────
Route::group(function () {
    // 文章收藏
    Route::post('article/addCollect',    [ApiArticleController::class, 'addCollect']);
    Route::post('article/cancelCollect', [ApiArticleController::class, 'cancelCollect']);
    Route::get('article/collect',        [ApiArticleController::class, 'collect']);

})->middleware([CheckTokenMiddleware::class]);
