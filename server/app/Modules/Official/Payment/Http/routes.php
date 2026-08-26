<?php
declare(strict_types=1);

use app\Modules\Official\Payment\ModuleProvider;
use app\Modules\Official\Payment\Http\Controller\PayConfigController;
use app\Modules\Official\Payment\Http\Controller\RechargeSettingController;
use app\Modules\Official\Payment\Http\Controller\RechargeController;
use app\Modules\Official\Payment\Http\Controller\RefundController;
use app\api\controller\PaymentNotifyController as ApiPaymentNotifyController;
use app\api\controller\RechargeController as ApiRechargeController;
use app\api\middleware\CheckTokenMiddleware;
use app\adminapi\http\middleware\AuthMiddleware;
use app\adminapi\http\middleware\LoginMiddleware;
use app\adminapi\http\middleware\OperationLogMiddleware;
use app\common\service\module\OfficialModuleMiddleware;
use think\facade\Route;

Route::group('api/admin', function (): void {
    Route::get('official.payment.settings.detail', [PayConfigController::class, 'getConfig']);
    Route::post('official.payment.settings.save', [PayConfigController::class, 'setConfig']);
    Route::get('official.payment.recharge-settings.detail', [RechargeSettingController::class, 'config']);
    Route::post('official.payment.recharge-settings.save', [RechargeSettingController::class, 'save']);
    Route::get('official.payment.recharge.list', [RechargeController::class, 'lists']);
    Route::post('official.payment.recharge.refund', [RechargeController::class, 'refund']);
    Route::post('official.payment.refund.retry', [RechargeController::class, 'refundAgain']);
    Route::get('official.payment.refund.stat', [RefundController::class, 'stat']);
    Route::get('official.payment.refund.list', [RefundController::class, 'record']);
    Route::get('official.payment.refund.log', [RefundController::class, 'log']);
})->middleware(LoginMiddleware::class)
    ->middleware(OfficialModuleMiddleware::class, (new ModuleProvider())->moduleKey(), 'http.admin')
    ->middleware(AuthMiddleware::class)
    ->middleware(OperationLogMiddleware::class);

Route::post('api/payment/notify/wechat/:binding', [ApiPaymentNotifyController::class, 'wechat']);
Route::post('api/payment/notify/alipay/:binding', [ApiPaymentNotifyController::class, 'alipay']);

Route::group('api', function (): void {
    Route::get('recharge/config', [ApiRechargeController::class, 'config']);
    Route::post('recharge/create', [ApiRechargeController::class, 'create']);
    Route::post('recharge/prepay', [ApiRechargeController::class, 'prepay']);
    Route::get('recharge/detail', [ApiRechargeController::class, 'detail']);
    Route::get('recharge/lists', [ApiRechargeController::class, 'lists']);
})->middleware(CheckTokenMiddleware::class)
    ->middleware(OfficialModuleMiddleware::class, (new ModuleProvider())->moduleKey(), 'http.member');
