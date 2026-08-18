<?php
declare(strict_types=1);

use app\Modules\Official\Notification\ModuleProvider;
use app\adminapi\controller\notice\NoticeChannelController;
use app\adminapi\controller\notice\NoticeLogController;
use app\adminapi\controller\notice\NoticeSceneController;
use app\api\controller\SmsController as ApiSmsController;
use app\api\middleware\PublicNoticeTenantMiddleware;
use app\adminapi\http\middleware\AuthMiddleware;
use app\adminapi\http\middleware\LoginMiddleware;
use app\adminapi\http\middleware\OperationLogMiddleware;
use app\common\service\module\OfficialModuleMiddleware;
use think\facade\Route;

Route::group('api/admin', function (): void {
    Route::get('notice/channel/detail', [NoticeChannelController::class, 'detail']);
    Route::post('notice/channel/save', [NoticeChannelController::class, 'save']);
    Route::get('notice/log/lists', [NoticeLogController::class, 'lists']);
    Route::get('notice/log/detail', [NoticeLogController::class, 'detail']);
    Route::get('notice/scene/lists', [NoticeSceneController::class, 'lists']);
    Route::get('notice/scene/detail', [NoticeSceneController::class, 'detail']);
    Route::post('notice/scene/save', [NoticeSceneController::class, 'save']);
})->middleware(LoginMiddleware::class)
    ->middleware(OfficialModuleMiddleware::class, (new ModuleProvider())->moduleKey(), 'http.admin')
    ->middleware(AuthMiddleware::class)
    ->middleware(OperationLogMiddleware::class);

Route::post('api/sms/sendCode', [ApiSmsController::class, 'sendCode'])
    ->middleware(PublicNoticeTenantMiddleware::class, 'notice.verification.send')
    ->middleware(OfficialModuleMiddleware::class, (new ModuleProvider())->moduleKey(), 'http.verification.send');
