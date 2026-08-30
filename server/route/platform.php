<?php
declare(strict_types=1);

use app\platform\controller\PlatformSessionController;
use app\platform\controller\PlatformAccessController;
use app\platform\controller\PlatformTenantBoundaryController;
use app\platform\controller\PlatformTenantController;
use app\platform\controller\PlatformTenantModuleController;
use app\platform\controller\PlatformControlPlaneQueryController;
use app\platform\controller\PlatformStorageController;
use app\platform\controller\PlatformOpsController;
use app\platform\controller\PlatformTenantInvitationController;
use app\platform\controller\PlatformTenantEntryBindingController;
use app\platform\controller\PlatformModuleLifecycleController;
use app\platform\controller\TenantOwnerInvitationPublicController;
use app\platform\http\middleware\PlatformLoginMiddleware;
use app\platform\http\middleware\PlatformHostMiddleware;
use app\platform\http\middleware\PlatformPermissionMiddleware;
use app\platform\http\middleware\PlatformInstanceToolMiddleware;
use think\facade\Route;

// Instance-local platform control plane. It never shares admin sessions, RBAC or routes.
Route::post('api/platform/session/login', [PlatformSessionController::class, 'login'])
    ->middleware(PlatformHostMiddleware::class);
Route::post('api/platform/session/refresh', [PlatformSessionController::class, 'refresh'])
    ->middleware(PlatformHostMiddleware::class);
Route::post('api/platform/session/logout', [PlatformSessionController::class, 'logout'])
    ->middleware(PlatformHostMiddleware::class);
Route::get('api/platform/session/info', [PlatformSessionController::class, 'info'])
    ->middleware(PlatformLoginMiddleware::class);
Route::get('api/platform/tenants/capabilities', [PlatformTenantBoundaryController::class, 'capabilities'])
    ->middleware(PlatformLoginMiddleware::class)
    ->middleware(PlatformPermissionMiddleware::class, 'platform.tenant.read');
Route::get('api/platform/tenants/detail', [PlatformTenantController::class, 'detail'])
    ->middleware(PlatformLoginMiddleware::class)
    ->middleware(PlatformPermissionMiddleware::class, 'platform.tenant.read');
Route::post('api/platform/tenants/provision', [PlatformTenantInvitationController::class, 'provision'])
    ->middleware(PlatformLoginMiddleware::class)
    ->middleware(PlatformPermissionMiddleware::class, 'platform.tenant.create');
Route::post('api/platform/tenants/invitations/resend', [PlatformTenantInvitationController::class, 'resend'])
    ->middleware(PlatformLoginMiddleware::class)
    ->middleware(PlatformPermissionMiddleware::class, 'platform.tenant.provision-owner');
Route::post('api/platform/tenants/invitations/revoke', [PlatformTenantInvitationController::class, 'revoke'])
    ->middleware(PlatformLoginMiddleware::class)
    ->middleware(PlatformPermissionMiddleware::class, 'platform.tenant.provision-owner');
// ThinkPHP routes are prefix-sensitive: register invitation actions before the collection route.
Route::post('api/platform/tenants/invitations', [PlatformTenantInvitationController::class, 'invite'])
    ->middleware(PlatformLoginMiddleware::class)
    ->middleware(PlatformPermissionMiddleware::class, 'platform.tenant.provision-owner');
Route::get('api/platform/tenants/invitations', [PlatformTenantInvitationController::class, 'lists'])
    ->middleware(PlatformLoginMiddleware::class)
    ->middleware(PlatformPermissionMiddleware::class, 'platform.tenant.provision-owner');
Route::get('api/platform/tenants/owner', [PlatformControlPlaneQueryController::class, 'owner'])
    ->middleware(PlatformLoginMiddleware::class)
    ->middleware(PlatformPermissionMiddleware::class, 'platform.tenant.read');
Route::get('api/platform/operators', [PlatformControlPlaneQueryController::class, 'operators'])
    ->middleware(PlatformLoginMiddleware::class)
    ->middleware(PlatformPermissionMiddleware::class, 'platform.operator.read');
Route::get('api/platform/roles', [PlatformControlPlaneQueryController::class, 'roles'])
    ->middleware(PlatformLoginMiddleware::class)
    ->middleware(PlatformPermissionMiddleware::class, 'platform.role.read');
Route::get('api/platform/permissions', [PlatformControlPlaneQueryController::class, 'permissions'])
    ->middleware(PlatformLoginMiddleware::class)
    ->middleware(PlatformPermissionMiddleware::class, 'platform.permission.read');
Route::get('api/platform/audit', [PlatformControlPlaneQueryController::class, 'audit'])
    ->middleware(PlatformLoginMiddleware::class)
    ->middleware(PlatformPermissionMiddleware::class, 'platform.audit.read');
Route::get('api/platform/tenants/modules', [PlatformControlPlaneQueryController::class, 'moduleStates'])
    ->middleware(PlatformLoginMiddleware::class)
    ->middleware(PlatformPermissionMiddleware::class, 'platform.tenant.read');
Route::get('api/platform/instance-tools/modules', [PlatformModuleLifecycleController::class, 'lists'])
    ->middleware(PlatformLoginMiddleware::class)
    ->middleware(PlatformPermissionMiddleware::class, 'platform.module.read')
    ->middleware(PlatformInstanceToolMiddleware::class);
Route::post('api/platform/instance-tools/modules/create', [PlatformModuleLifecycleController::class, 'create'])
    ->middleware(PlatformLoginMiddleware::class)
    ->middleware(PlatformPermissionMiddleware::class, 'platform.module.create')
    ->middleware(PlatformInstanceToolMiddleware::class);
Route::post('api/platform/instance-tools/modules/install', [PlatformModuleLifecycleController::class, 'install'])
    ->middleware(PlatformLoginMiddleware::class)
    ->middleware(PlatformPermissionMiddleware::class, 'platform.module.install')
    ->middleware(PlatformInstanceToolMiddleware::class);
Route::post('api/platform/instance-tools/modules/uninstall', [PlatformModuleLifecycleController::class, 'uninstall'])
    ->middleware(PlatformLoginMiddleware::class)
    ->middleware(PlatformPermissionMiddleware::class, 'platform.module.uninstall')
    ->middleware(PlatformInstanceToolMiddleware::class);
Route::post('api/platform/instance-tools/modules/disable', [PlatformModuleLifecycleController::class, 'disable'])
    ->middleware(PlatformLoginMiddleware::class)
    ->middleware(PlatformPermissionMiddleware::class, 'platform.module.disable')
    ->middleware(PlatformInstanceToolMiddleware::class);
Route::post('api/platform/instance-tools/modules/sync', [PlatformModuleLifecycleController::class, 'sync'])
    ->middleware(PlatformLoginMiddleware::class)
    ->middleware(PlatformPermissionMiddleware::class, 'platform.module.sync')
    ->middleware(PlatformInstanceToolMiddleware::class);
Route::get('api/platform/tenant-entry-bindings', [PlatformTenantEntryBindingController::class, 'lists'])
    ->middleware(PlatformLoginMiddleware::class)
    ->middleware(PlatformPermissionMiddleware::class, 'platform.tenant.read');
Route::post('api/platform/tenant-entry-bindings/enable', [PlatformTenantEntryBindingController::class, 'enable'])
    ->middleware(PlatformLoginMiddleware::class)
    ->middleware(PlatformPermissionMiddleware::class, 'platform.tenant.update');
Route::get('api/platform/infrastructure/storage', [PlatformStorageController::class, 'snapshot'])
    ->middleware(PlatformLoginMiddleware::class)
    ->middleware(PlatformPermissionMiddleware::class, 'platform.ops.read');
Route::get('api/platform/v1/ops/status', [PlatformOpsController::class, 'status'])
    ->middleware(PlatformLoginMiddleware::class)
    ->middleware(PlatformPermissionMiddleware::class, 'platform.ops.read');
Route::get('api/platform/v1/ops/upgrade-readiness', [PlatformOpsController::class, 'upgradeReadiness'])
    ->middleware(PlatformLoginMiddleware::class)
    ->middleware(PlatformPermissionMiddleware::class, 'platform.ops.read');
Route::get('api/platform/v1/ops/providers', [PlatformOpsController::class, 'providers'])
    ->middleware(PlatformLoginMiddleware::class)
    ->middleware(PlatformPermissionMiddleware::class, 'platform.ops.read');
Route::get('api/platform/v1/ops/maintenance', [PlatformOpsController::class, 'maintenance'])
    ->middleware(PlatformLoginMiddleware::class)
    ->middleware(PlatformPermissionMiddleware::class, 'platform.ops.read');
Route::put('api/platform/v1/ops/maintenance', [PlatformOpsController::class, 'scheduleMaintenance'])
    ->middleware(PlatformLoginMiddleware::class)
    ->middleware(PlatformPermissionMiddleware::class, 'platform.ops.maintenance.manage');
Route::post('api/platform/v1/ops/maintenance/:maintenance_key/close', [PlatformOpsController::class, 'closeMaintenance'])
    ->middleware(PlatformLoginMiddleware::class)
    ->middleware(PlatformPermissionMiddleware::class, 'platform.ops.maintenance.manage');
Route::get('api/platform/v1/ops/diagnostics', [PlatformOpsController::class, 'diagnostics'])
    ->middleware(PlatformLoginMiddleware::class)
    ->middleware(PlatformPermissionMiddleware::class, 'platform.ops.read')
    ->middleware(PlatformPermissionMiddleware::class, 'platform.ops.logs.read');
Route::post('api/platform/v1/ops/tasks/backup', [PlatformOpsController::class, 'backup'])
    ->middleware(PlatformLoginMiddleware::class)
    ->middleware(PlatformPermissionMiddleware::class, 'platform.ops.backup.manage');
Route::post('api/platform/v1/ops/tasks/restore', [PlatformOpsController::class, 'restore'])
    ->middleware(PlatformLoginMiddleware::class)
    ->middleware(PlatformPermissionMiddleware::class, 'platform.ops.restore.manage');
Route::post('api/platform/v1/ops/tasks/upgrade', [PlatformOpsController::class, 'upgrade'])
    ->middleware(PlatformLoginMiddleware::class)
    ->middleware(PlatformPermissionMiddleware::class, 'platform.ops.read')
    ->middleware(PlatformPermissionMiddleware::class, 'platform.ops.upgrade.manage');
Route::post('api/platform/v1/ops/tasks/module', [PlatformOpsController::class, 'moduleOperation'])
    ->middleware(PlatformLoginMiddleware::class)
    ->middleware(PlatformPermissionMiddleware::class, 'platform.ops.read')
    ->middleware(PlatformPermissionMiddleware::class, 'platform.ops.module.manage');
Route::get('api/platform/v1/ops/modules', [PlatformOpsController::class, 'moduleOperations'])
    ->middleware(PlatformLoginMiddleware::class)
    ->middleware(PlatformPermissionMiddleware::class, 'platform.ops.read');
Route::get('api/platform/v1/ops/upgrades', [PlatformOpsController::class, 'upgrades'])
    ->middleware(PlatformLoginMiddleware::class)
    ->middleware(PlatformPermissionMiddleware::class, 'platform.ops.read');
Route::get('api/platform/v1/ops/backups', [PlatformOpsController::class, 'backups'])
    ->middleware(PlatformLoginMiddleware::class)
    ->middleware(PlatformPermissionMiddleware::class, 'platform.ops.read');
Route::get('api/platform/v1/ops/tasks/:task_key', [PlatformOpsController::class, 'task'])
    ->middleware(PlatformLoginMiddleware::class)
    ->middleware(PlatformPermissionMiddleware::class, 'platform.ops.read');
Route::post('api/platform/infrastructure/storage/account', [PlatformStorageController::class, 'createAccount'])
    ->middleware(PlatformLoginMiddleware::class)
    ->middleware(PlatformPermissionMiddleware::class, 'platform.ops.maintenance.manage');
Route::post('api/platform/infrastructure/storage/account/update', [PlatformStorageController::class, 'updateAccount'])
    ->middleware(PlatformLoginMiddleware::class)
    ->middleware(PlatformPermissionMiddleware::class, 'platform.ops.maintenance.manage');
Route::post('api/platform/infrastructure/storage/space', [PlatformStorageController::class, 'createSpace'])
    ->middleware(PlatformLoginMiddleware::class)
    ->middleware(PlatformPermissionMiddleware::class, 'platform.ops.maintenance.manage');
Route::post('api/platform/infrastructure/storage/space/update', [PlatformStorageController::class, 'updateSpace'])
    ->middleware(PlatformLoginMiddleware::class)
    ->middleware(PlatformPermissionMiddleware::class, 'platform.ops.maintenance.manage');
Route::post('api/platform/infrastructure/storage/route', [PlatformStorageController::class, 'setRoute'])
    ->middleware(PlatformLoginMiddleware::class)
    ->middleware(PlatformPermissionMiddleware::class, 'platform.ops.maintenance.manage');
Route::post('api/platform/tenant-entry-bindings/disable', [PlatformTenantEntryBindingController::class, 'disable'])
    ->middleware(PlatformLoginMiddleware::class)
    ->middleware(PlatformPermissionMiddleware::class, 'platform.tenant.update');
Route::get('api/tenant/owner-invitations/inspect', [TenantOwnerInvitationPublicController::class, 'inspect'])
    ->middleware(PlatformHostMiddleware::class);
Route::post('api/tenant/owner-invitations/accept', [TenantOwnerInvitationPublicController::class, 'accept'])
    ->middleware(PlatformHostMiddleware::class);
Route::post('api/platform/tenants/activate', [PlatformTenantController::class, 'activate'])
    ->middleware(PlatformLoginMiddleware::class)
    ->middleware(PlatformPermissionMiddleware::class, 'platform.tenant.lifecycle');
Route::post('api/platform/tenants/suspend', [PlatformTenantController::class, 'suspend'])
    ->middleware(PlatformLoginMiddleware::class)
    ->middleware(PlatformPermissionMiddleware::class, 'platform.tenant.lifecycle');
Route::post('api/platform/tenants/close', [PlatformTenantController::class, 'close'])
    ->middleware(PlatformLoginMiddleware::class)
    ->middleware(PlatformPermissionMiddleware::class, 'platform.tenant.lifecycle');
Route::post('api/platform/tenants/modules/enable', [PlatformTenantModuleController::class, 'enable'])
    ->middleware(PlatformLoginMiddleware::class)
    ->middleware(PlatformPermissionMiddleware::class, 'platform.tenant.module.manage');
Route::post('api/platform/tenants/modules/disable', [PlatformTenantModuleController::class, 'disable'])
    ->middleware(PlatformLoginMiddleware::class)
    ->middleware(PlatformPermissionMiddleware::class, 'platform.tenant.module.manage');
// ThinkPHP routes are prefix-sensitive: register the generic Tenant list after every /tenants/* route.
Route::get('api/platform/tenants', [PlatformTenantController::class, 'lists'])
    ->middleware(PlatformLoginMiddleware::class)
    ->middleware(PlatformPermissionMiddleware::class, 'platform.tenant.read');
Route::post('api/platform/operators/create', [PlatformAccessController::class, 'createOperator'])
    ->middleware(PlatformLoginMiddleware::class)
    ->middleware(PlatformPermissionMiddleware::class, 'platform.operator.create');
Route::post('api/platform/operators/update', [PlatformAccessController::class, 'updateOperator'])
    ->middleware(PlatformLoginMiddleware::class)
    ->middleware(PlatformPermissionMiddleware::class, 'platform.operator.update');
Route::post('api/platform/operators/roles/replace', [PlatformAccessController::class, 'replaceOperatorRoles'])
    ->middleware(PlatformLoginMiddleware::class)
    ->middleware(PlatformPermissionMiddleware::class, 'platform.operator.role.assign');
Route::post('api/platform/operators/activate', [PlatformAccessController::class, 'activateOperator'])
    ->middleware(PlatformLoginMiddleware::class)
    ->middleware(PlatformPermissionMiddleware::class, 'platform.operator.lifecycle');
Route::post('api/platform/operators/suspend', [PlatformAccessController::class, 'suspendOperator'])
    ->middleware(PlatformLoginMiddleware::class)
    ->middleware(PlatformPermissionMiddleware::class, 'platform.operator.lifecycle');
Route::post('api/platform/operators/close', [PlatformAccessController::class, 'closeOperator'])
    ->middleware(PlatformLoginMiddleware::class)
    ->middleware(PlatformPermissionMiddleware::class, 'platform.operator.lifecycle');
Route::post('api/platform/roles/create', [PlatformAccessController::class, 'createRole'])
    ->middleware(PlatformLoginMiddleware::class)
    ->middleware(PlatformPermissionMiddleware::class, 'platform.role.create');
Route::post('api/platform/roles/update', [PlatformAccessController::class, 'updateRole'])
    ->middleware(PlatformLoginMiddleware::class)
    ->middleware(PlatformPermissionMiddleware::class, 'platform.role.update');
Route::post('api/platform/roles/archive', [PlatformAccessController::class, 'archiveRole'])
    ->middleware(PlatformLoginMiddleware::class)
    ->middleware(PlatformPermissionMiddleware::class, 'platform.role.archive');
Route::post('api/platform/roles/permissions/replace', [PlatformAccessController::class, 'replaceRolePermissions'])
    ->middleware(PlatformLoginMiddleware::class)
    ->middleware(PlatformPermissionMiddleware::class, 'platform.role.permission.assign');
