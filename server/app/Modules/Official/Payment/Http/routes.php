<?php
declare(strict_types=1);

use app\Modules\Official\Payment\ModuleProvider;
use app\adminapi\controller\setting\PayConfigController;
use app\adminapi\controller\setting\RechargeSettingController;
use app\adminapi\controller\finance\RechargeController;
use app\adminapi\controller\finance\RefundController;
use app\api\controller\PaymentNotifyController as ApiPaymentNotifyController;
use app\api\controller\RechargeController as ApiRechargeController;
use app\api\middleware\CheckTokenMiddleware;
use app\adminapi\http\middleware\AuthMiddleware;
use app\adminapi\http\middleware\LoginMiddleware;
use app\adminapi\http\middleware\OperationLogMiddleware;
use app\common\service\module\OfficialModuleMiddleware;
use think\facade\Route;

Route::group('api/admin', function (): void {
    Route::get('setting/pay/config', [PayConfigController::class, 'getConfig']);
    Route::post('setting/pay/save', [PayConfigController::class, 'setConfig']);
    Route::get('setting/recharge/config', [RechargeSettingController::class, 'config']);
    Route::post('setting/recharge/save', [RechargeSettingController::class, 'save']);
    Route::get('finance/recharge/lists', [RechargeController::class, 'lists']);
    Route::post('finance/recharge/refund', [RechargeController::class, 'refund']);
    Route::post('finance/recharge/refundAgain', [RechargeController::class, 'refundAgain']);
    Route::get('recharge.recharge/lists', [RechargeController::class, 'lists']);
    Route::post('recharge.recharge/refund', [RechargeController::class, 'refund']);
    Route::post('recharge.recharge/refundAgain', [RechargeController::class, 'refundAgain']);
    Route::get('finance/refund/stat', [RefundController::class, 'stat']);
    Route::get('finance/refund/record', [RefundController::class, 'record']);
    Route::get('finance/refund/log', [RefundController::class, 'log']);
    Route::get('finance.refund/stat', [RefundController::class, 'stat']);
    Route::get('finance.refund/record', [RefundController::class, 'record']);
    Route::get('finance.refund/log', [RefundController::class, 'log']);
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
