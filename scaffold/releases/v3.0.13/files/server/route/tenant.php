<?php
declare(strict_types=1);

use app\tenant\controller\TenantSessionController;
use think\facade\Route;

// Multi-tenant Admin session boundary. Core owns selection challenges and atomic old-session revocation.
Route::post('api/tenant/session/login', [TenantSessionController::class, 'login']);
Route::post('api/tenant/session/select', [TenantSessionController::class, 'select']);
Route::post('api/tenant/session/switch', [TenantSessionController::class, 'switchChallenge']);
Route::post('api/tenant/session/refresh', [TenantSessionController::class, 'refresh']);
Route::post('api/tenant/session/logout', [TenantSessionController::class, 'logout']);
