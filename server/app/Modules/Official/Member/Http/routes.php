<?php
declare(strict_types=1);

use app\Modules\Official\Member\ModuleProvider;
use app\adminapi\controller\member\MemberController;
use app\adminapi\controller\member\MemberTagController;
use app\adminapi\controller\finance\AccountLogController;
use app\api\controller\LoginController as ApiLoginController;
use app\api\controller\UserController as ApiUserController;
use app\api\controller\AccountLogController as ApiAccountLogController;
use app\api\middleware\CheckTokenMiddleware;
use app\api\middleware\PublicMemberTenantMiddleware;
use app\api\middleware\PublicNoticeTenantMiddleware;
use app\adminapi\http\middleware\AuthMiddleware;
use app\adminapi\http\middleware\LoginMiddleware;
use app\adminapi\http\middleware\OperationLogMiddleware;
use app\common\service\module\OfficialModuleMiddleware;
use think\facade\Route;

Route::group('api/admin', function (): void {
    Route::get('member/lists', [MemberController::class, 'lists']);
    Route::get('member/detail', [MemberController::class, 'detail']);
    Route::post('member/add', [MemberController::class, 'add']);
    Route::post('member/edit', [MemberController::class, 'edit']);
    Route::post('member/profile/edit', [MemberController::class, 'profileEdit']);
    Route::post('member/status', [MemberController::class, 'updateStatus']);
    Route::post('member/adjustBalance', [MemberController::class, 'adjustBalance']);
    Route::post('member/adjustMoney', [MemberController::class, 'adjustMoney']);
    Route::get('user.user/detail', [MemberController::class, 'detail']);
    Route::post('user.user/edit', [MemberController::class, 'edit']);
    Route::post('user.user/adjustMoney', [MemberController::class, 'adjustMoney']);
    Route::get('member/tag/lists', [MemberTagController::class, 'lists']);
    Route::post('member/tag/add', [MemberTagController::class, 'add']);
    Route::post('member/tag/edit', [MemberTagController::class, 'edit']);
    Route::post('member/tag/delete', [MemberTagController::class, 'delete']);
    Route::get('finance/account-log/lists', [AccountLogController::class, 'lists']);
    Route::get('finance/account-log/change-types', [AccountLogController::class, 'getUmChangeType']);
    Route::get('finance.account_log/lists', [AccountLogController::class, 'lists']);
    Route::get('finance.account_log/getUmChangeType', [AccountLogController::class, 'getUmChangeType']);
})->middleware(LoginMiddleware::class)
    ->middleware(OfficialModuleMiddleware::class, (new ModuleProvider())->moduleKey(), 'http.admin')
    ->middleware(AuthMiddleware::class)
    ->middleware(OperationLogMiddleware::class);

Route::post('api/login/register', [ApiLoginController::class, 'register'])
    ->middleware(PublicMemberTenantMiddleware::class, 'member.register')
    ->middleware(OfficialModuleMiddleware::class, (new ModuleProvider())->moduleKey(), 'http.register');
Route::post('api/login/account', [ApiLoginController::class, 'account'])
    ->middleware(PublicMemberTenantMiddleware::class, 'member.login')
    ->middleware(OfficialModuleMiddleware::class, (new ModuleProvider())->moduleKey(), 'http.login');
foreach ([
    ['api/login/mobile', 'mobile', 'notice.verification.verify', 'http.mobile-login'],
    ['api/login/resetPassword', 'resetPassword', 'notice.verification.verify', 'http.reset-password'],
] as [$path, $action, $noticeOperation, $memberOperation]) {
    Route::post($path, [ApiLoginController::class, $action])
        ->middleware(PublicNoticeTenantMiddleware::class, $noticeOperation)
        ->middleware(OfficialModuleMiddleware::class, 'official.notification', $noticeOperation)
        ->middleware(OfficialModuleMiddleware::class, (new ModuleProvider())->moduleKey(), $memberOperation);
}

Route::group('api', function (): void {
    Route::get('user/center', [ApiUserController::class, 'center']);
    Route::get('user/info', [ApiUserController::class, 'info']);
    Route::post('user/setInfo', [ApiUserController::class, 'setInfo']);
    Route::post('user/changePassword', [ApiUserController::class, 'changePassword']);
    Route::post('user/bindMobile', [ApiUserController::class, 'bindMobile']);
    Route::get('account_log/lists', [ApiAccountLogController::class, 'lists']);
})->middleware(CheckTokenMiddleware::class)
    ->middleware(OfficialModuleMiddleware::class, (new ModuleProvider())->moduleKey(), 'http.member');
