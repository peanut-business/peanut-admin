<?php
declare(strict_types=1);

use app\adminapi\controller\auth\LoginController;
use app\installation\controller\InstallationController;
use think\facade\Route;

// Anonymous bootstrap and login entries are registered before every identity boundary.
Route::get('api/installation/status', [InstallationController::class, 'status']);
Route::post('api/installation/execute', [InstallationController::class, 'execute']);
Route::post('api/user/login', [LoginController::class, 'login']);
Route::post('api/user/logout', [LoginController::class, 'logout']);
Route::post('admin/login/login', [LoginController::class, 'login']);
Route::post('admin/login/logout', [LoginController::class, 'logout']);

require __DIR__ . '/platform.php';
require __DIR__ . '/tenant.php';
require __DIR__ . '/admin.php';
require __DIR__ . '/public_api.php';

// Optional official Modules own their HTTP entries. The application route file
// remains the single bootstrap entry, while each Module keeps its routes beside
// its provider, menu and permission contracts.
foreach ([
    'official_article.php',
    'official_file.php',
    'official_notification.php',
    'official_oauth.php',
    'official_payment.php',
    'official_member.php',
    'official_task.php',
    'official_import_export.php',
] as $moduleRoute) {
    require __DIR__ . '/' . $moduleRoute;
}
