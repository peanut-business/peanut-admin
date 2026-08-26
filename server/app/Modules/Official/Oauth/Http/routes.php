<?php
declare(strict_types=1);

use app\Modules\Official\Oauth\ModuleProvider;
use app\api\controller\OAuthController as ApiOAuthController;
use app\api\controller\OfficialAccountController as ApiOfficialAccountController;
use app\api\middleware\CheckTokenMiddleware;
use app\Modules\Official\Oauth\Http\Controller\WebPageController;
use app\Modules\Official\Oauth\Http\Controller\MiniProgramController;
use app\Modules\Official\Oauth\Http\Controller\OfficialAccountController;
use app\Modules\Official\Oauth\Http\Controller\OfficialAccountMenuController;
use app\Modules\Official\Oauth\Http\Controller\OfficialAccountReplyController;
use app\Modules\Official\Oauth\Http\Controller\OpenPlatformController;
use app\adminapi\http\middleware\AuthMiddleware;
use app\adminapi\http\middleware\LoginMiddleware;
use app\adminapi\http\middleware\OperationLogMiddleware;
use app\common\service\module\OfficialModuleMiddleware;
use think\facade\Route;

Route::group('api/admin', function (): void {
    Route::get('official.oauth.web-page.config', [WebPageController::class, 'getConfig']);
    Route::post('official.oauth.web-page.save', [WebPageController::class, 'setConfig']);
    Route::get('official.oauth.mini-program.config', [MiniProgramController::class, 'getConfig']);
    Route::post('official.oauth.mini-program.save', [MiniProgramController::class, 'setConfig']);
    Route::get('official.oauth.official-account.config', [OfficialAccountController::class, 'getConfig']);
    Route::post('official.oauth.official-account.save', [OfficialAccountController::class, 'setConfig']);
    Route::get('official.oauth.official-account.menu.detail', [OfficialAccountMenuController::class, 'detail']);
    Route::post('official.oauth.official-account.menu.save', [OfficialAccountMenuController::class, 'save']);
    Route::post('official.oauth.official-account.menu.publish', [OfficialAccountMenuController::class, 'saveAndPublish']);
    Route::get('official.oauth.official-account.reply.list', [OfficialAccountReplyController::class, 'lists']);
    Route::get('official.oauth.official-account.reply.detail', [OfficialAccountReplyController::class, 'detail']);
    Route::post('official.oauth.official-account.reply.add', [OfficialAccountReplyController::class, 'add']);
    Route::post('official.oauth.official-account.reply.edit', [OfficialAccountReplyController::class, 'edit']);
    Route::post('official.oauth.official-account.reply.delete', [OfficialAccountReplyController::class, 'delete']);
    Route::post('official.oauth.official-account.reply.update-status', [OfficialAccountReplyController::class, 'updateStatus']);
    Route::get('official.oauth.open-platform.config', [OpenPlatformController::class, 'getConfig']);
    Route::post('official.oauth.open-platform.save', [OpenPlatformController::class, 'setConfig']);
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
