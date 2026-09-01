<?php
declare(strict_types=1);

use app\Modules\Official\Notification\ModuleProvider;
use app\Modules\Official\Notification\Http\Controller\NoticeChannelController;
use app\Modules\Official\Notification\Http\Controller\NoticeLogController;
use app\Modules\Official\Notification\Http\Controller\NoticeSceneController;
use app\api\controller\SmsController as ApiSmsController;
use app\api\middleware\PublicNoticeTenantMiddleware;
use app\adminapi\http\middleware\AuthMiddleware;
use app\adminapi\http\middleware\LoginMiddleware;
use app\adminapi\http\middleware\OperationLogMiddleware;
use app\common\service\module\OfficialModuleMiddleware;
use think\facade\Route;

if (($peanutRouteApplication ?? null) === 'adminapi') {
Route::group(function (): void {
    Route::get('official.notification.channel.detail', [NoticeChannelController::class, 'detail']);
    Route::post('official.notification.channel.save', [NoticeChannelController::class, 'save']);
    Route::get('official.notification.log.list', [NoticeLogController::class, 'lists']);
    Route::get('official.notification.log.detail', [NoticeLogController::class, 'detail']);
    Route::get('official.notification.scene.list', [NoticeSceneController::class, 'lists']);
    Route::get('official.notification.scene.detail', [NoticeSceneController::class, 'detail']);
    Route::post('official.notification.scene.save', [NoticeSceneController::class, 'save']);
})->middleware(LoginMiddleware::class)
    ->middleware(OfficialModuleMiddleware::class, (new ModuleProvider())->moduleKey(), 'http.admin')
    ->middleware(AuthMiddleware::class)
    ->middleware(OperationLogMiddleware::class);
}

if (($peanutRouteApplication ?? null) === 'api') {
Route::post('sms/sendCode', [ApiSmsController::class, 'sendCode'])
    ->middleware(PublicNoticeTenantMiddleware::class, 'notice.verification.send')
    ->middleware(OfficialModuleMiddleware::class, (new ModuleProvider())->moduleKey(), 'http.verification.send');
}
