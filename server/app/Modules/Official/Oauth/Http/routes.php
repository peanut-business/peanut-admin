<?php
declare(strict_types=1);

use app\Modules\Official\Oauth\ModuleProvider;
use app\api\controller\OAuthController as ApiOAuthController;
use app\api\controller\OfficialAccountController as ApiOfficialAccountController;
use app\api\middleware\CheckTokenMiddleware;
use app\adminapi\controller\setting\WebPageController;
use app\adminapi\controller\setting\MiniProgramController;
use app\adminapi\controller\setting\OfficialAccountController;
use app\adminapi\controller\setting\OfficialAccountMenuController;
use app\adminapi\controller\setting\OfficialAccountReplyController;
use app\adminapi\controller\setting\OpenPlatformController;
use app\adminapi\http\middleware\AuthMiddleware;
use app\adminapi\http\middleware\LoginMiddleware;
use app\adminapi\http\middleware\OperationLogMiddleware;
use app\common\service\module\OfficialModuleMiddleware;
use think\facade\Route;

Route::group('api/admin', function (): void {
    Route::get('setting/web-page/config', [WebPageController::class, 'getConfig']);
    Route::post('setting/web-page/save', [WebPageController::class, 'setConfig']);
    Route::get('setting/mini-program/config', [MiniProgramController::class, 'getConfig']);
    Route::post('setting/mini-program/save', [MiniProgramController::class, 'setConfig']);
    Route::get('setting/official-account/config', [OfficialAccountController::class, 'getConfig']);
    Route::post('setting/official-account/save', [OfficialAccountController::class, 'setConfig']);
    Route::get('setting/official-account/menu', [OfficialAccountMenuController::class, 'detail']);
    Route::post('setting/official-account/menu/save', [OfficialAccountMenuController::class, 'save']);
    Route::post('setting/official-account/menu/publish', [OfficialAccountMenuController::class, 'saveAndPublish']);
    Route::get('setting/official-account/reply/lists', [OfficialAccountReplyController::class, 'lists']);
    Route::get('setting/official-account/reply/detail', [OfficialAccountReplyController::class, 'detail']);
    Route::post('setting/official-account/reply/add', [OfficialAccountReplyController::class, 'add']);
    Route::post('setting/official-account/reply/edit', [OfficialAccountReplyController::class, 'edit']);
    Route::post('setting/official-account/reply/delete', [OfficialAccountReplyController::class, 'delete']);
    Route::post('setting/official-account/reply/status', [OfficialAccountReplyController::class, 'updateStatus']);
    Route::get('setting/open-platform/config', [OpenPlatformController::class, 'getConfig']);
    Route::post('setting/open-platform/save', [OpenPlatformController::class, 'setConfig']);
})->middleware(LoginMiddleware::class)
    ->middleware(OfficialModuleMiddleware::class, (new ModuleProvider())->moduleKey(), 'http.admin')
    ->middleware(AuthMiddleware::class)
    ->middleware(OperationLogMiddleware::class);

Route::post('api/oauth/wechat/begin', [ApiOAuthController::class, 'begin']);
Route::post('api/oauth/wechat/callback', [ApiOAuthController::class, 'callback']);
Route::post('api/oauth/wechat/mini-program', [ApiOAuthController::class, 'miniProgram']);
Route::post('api/oauth/wechat/complete', [ApiOAuthController::class, 'complete']);
Route::get('api/oauth/wechat/redirect/pc', [ApiOAuthController::class, 'redirectPc']);
Route::get('api/oauth/wechat/redirect/official-account', [ApiOAuthController::class, 'redirectOfficialAccount']);
Route::post('api/oauth/wechat/bind', [ApiOAuthController::class, 'bind'])
    ->middleware(CheckTokenMiddleware::class)
    ->middleware(OfficialModuleMiddleware::class, (new ModuleProvider())->moduleKey(), 'http.member-bind');
Route::get('api/wechat/official-account/callback/:binding', [ApiOfficialAccountController::class, 'verify']);
Route::post('api/wechat/official-account/callback/:binding', [ApiOfficialAccountController::class, 'callback']);
