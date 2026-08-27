<?php
declare(strict_types=1);

use app\adminapi\controller\auth\AdminController;
use app\adminapi\controller\auth\LoginController;
use app\adminapi\controller\auth\MenuController;
use app\adminapi\controller\auth\RoleController;
use app\adminapi\controller\WorkbenchController;
use app\api\controller\IndexController as ApiIndexController;
use app\api\controller\LoginController as ApiLoginController;
use app\api\controller\ArticleController as ApiArticleController;
use app\api\controller\SearchController as ApiSearchController;
use app\api\controller\StorageController as ApiStorageController;
use app\api\controller\PcController as ApiPcController;
use app\api\controller\DecorationController as ApiDecorationController;
use app\api\middleware\CheckTokenMiddleware;
use app\api\middleware\PublicArticleTenantMiddleware;
use app\api\middleware\PublicDecorationTenantMiddleware;
use app\api\middleware\PublicHotSearchTenantMiddleware;
use app\adminapi\controller\config\ConfigController;
use app\adminapi\controller\config\ReadinessController;
use app\adminapi\controller\dept\DeptController;
use app\adminapi\controller\dept\JobsController;
use app\adminapi\controller\dict\DictTypeController;
use app\adminapi\controller\dict\DictDataController;
use app\adminapi\controller\generator\GeneratorController;
use app\adminapi\controller\system\SystemController;
use app\adminapi\controller\setting\HotSearchController;
use app\adminapi\controller\setting\TransactionSettingsController;
use app\adminapi\controller\decoration\DecorationPageController;
use app\adminapi\controller\decoration\DecorationTabbarController;
use app\adminapi\controller\log\OperationLogController;
use app\adminapi\http\middleware\AuthMiddleware;
use app\adminapi\http\middleware\LoginMiddleware;
use app\adminapi\http\middleware\OperationLogMiddleware;
use app\Modules\Official\Article\Http\ArticleModuleMiddleware;
use app\installation\controller\InstallationController;
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
use app\tenant\controller\TenantSessionController;
use think\facade\Route;

// ─── 免登录路由（不挂任何鉴权中间件） ──────────────────────────────────────
Route::get('api/installation/status', [InstallationController::class, 'status']);
Route::post('api/installation/execute', [InstallationController::class, 'execute']);
Route::post('api/user/login',  [LoginController::class, 'login']);
Route::post('api/user/logout', [LoginController::class, 'logout']);
Route::post('admin/login/login',  [LoginController::class, 'login']);
Route::post('admin/login/logout', [LoginController::class, 'logout']);

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
Route::get('api/platform/v1/ops/maintenance', [PlatformOpsController::class, 'maintenance'])
    ->middleware(PlatformLoginMiddleware::class)
    ->middleware(PlatformPermissionMiddleware::class, 'platform.ops.read');
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

// Multi-tenant Admin session boundary. Core owns selection challenges and atomic old-session revocation.
Route::post('api/tenant/session/login', [TenantSessionController::class, 'login']);
Route::post('api/tenant/session/select', [TenantSessionController::class, 'select']);
Route::post('api/tenant/session/switch', [TenantSessionController::class, 'switchChallenge']);
Route::post('api/tenant/session/refresh', [TenantSessionController::class, 'refresh']);
Route::post('api/tenant/session/logout', [TenantSessionController::class, 'logout']);

// ─── 管理端会话与菜单路由（仅需登录，不做 RBAC） ───────────────────────────
Route::group(function () {
    Route::post('api/user/info', [LoginController::class, 'info']);
    Route::post('api/user/menu', [MenuController::class, 'route']);
})->middleware(LoginMiddleware::class);

// ─── 管理后台完整 API（Login → Auth 两层中间件） ─────────────────────────────
// 前缀统一挂在 api/ 下，前端 vite 代理只转发 /api，生产 nginx 也只需转一条前缀。
Route::group('api/admin', function () {
    Route::get('login/info', [LoginController::class, 'info']);

    // 工作台
    Route::get('workbench/index', [WorkbenchController::class, 'index']);

    // 菜单
    Route::get('menu/route',   [MenuController::class, 'route']);
    Route::get('menu/lists',   [MenuController::class, 'lists']);
    Route::get('menu/all',     [MenuController::class, 'all']);
    Route::get('menu/detail',  [MenuController::class, 'detail']);
    Route::post('menu/add',    [MenuController::class, 'add']);
    Route::post('menu/edit',   [MenuController::class, 'edit']);
    Route::post('menu/delete', [MenuController::class, 'delete']);
    Route::post('menu/status', [MenuController::class, 'updateStatus']);

    // 角色
    Route::get('role/lists',   [RoleController::class, 'lists']);
    Route::get('role/all',     [RoleController::class, 'all']);
    Route::get('role/detail',  [RoleController::class, 'detail']);
    Route::post('role/add',    [RoleController::class, 'add']);
    Route::post('role/edit',   [RoleController::class, 'edit']);
    Route::post('role/delete', [RoleController::class, 'delete']);

    // 管理员
    Route::get('admin/lists',   [AdminController::class, 'lists']);
    Route::get('admin/detail',  [AdminController::class, 'detail']);
    Route::get('admin/self',    [AdminController::class, 'self']);
    Route::post('admin/editSelf', [AdminController::class, 'editSelf']);
    Route::post('admin/add',    [AdminController::class, 'add']);
    Route::post('admin/edit',   [AdminController::class, 'edit']);
    Route::post('admin/delete', [AdminController::class, 'delete']);
    Route::post('admin/status', [AdminController::class, 'updateStatus']);

    // 部门
    Route::get('dept/lists',   [DeptController::class, 'lists']);
    Route::get('dept/all',     [DeptController::class, 'all']);
    Route::get('dept/leaderDept', [DeptController::class, 'leaderDept']);
    Route::get('dept/detail',  [DeptController::class, 'detail']);
    Route::post('dept/add',    [DeptController::class, 'add']);
    Route::post('dept/edit',   [DeptController::class, 'edit']);
    Route::post('dept/delete', [DeptController::class, 'delete']);
    Route::post('dept/status', [DeptController::class, 'updateStatus']);

    // 岗位
    Route::get('jobs/lists',   [JobsController::class, 'lists']);
    Route::get('jobs/all',     [JobsController::class, 'all']);
    Route::get('jobs/detail',  [JobsController::class, 'detail']);
    Route::post('jobs/add',    [JobsController::class, 'add']);
    Route::post('jobs/edit',   [JobsController::class, 'edit']);
    Route::post('jobs/delete', [JobsController::class, 'delete']);
    Route::post('jobs/status', [JobsController::class, 'updateStatus']);

    // 字典类型
    Route::get('dict/type/lists',   [DictTypeController::class, 'lists']);
    Route::get('dict/type/all',     [DictTypeController::class, 'all']);
    Route::get('dict/type/detail',  [DictTypeController::class, 'detail']);
    Route::post('dict/type/add',    [DictTypeController::class, 'add']);
    Route::post('dict/type/edit',   [DictTypeController::class, 'edit']);
    Route::post('dict/type/delete', [DictTypeController::class, 'delete']);
    Route::post('dict/type/status', [DictTypeController::class, 'updateStatus']);

    // 字典数据
    Route::get('dict/data/lists',   [DictDataController::class, 'lists']);
    Route::get('dict/data/byType',  [DictDataController::class, 'byType']);
    Route::get('dict/data/detail',  [DictDataController::class, 'detail']);
    Route::post('dict/data/add',    [DictDataController::class, 'add']);
    Route::post('dict/data/edit',   [DictDataController::class, 'edit']);
    Route::post('dict/data/delete', [DictDataController::class, 'delete']);
    Route::post('dict/data/status', [DictDataController::class, 'updateStatus']);

    // 开发工具 - 安全代码生成器
    Route::get('generator/source-tables', [GeneratorController::class, 'sourceTables']);
    Route::get('generator/lists', [GeneratorController::class, 'lists']);
    Route::get('generator/detail', [GeneratorController::class, 'detail']);
    Route::post('generator/import', [GeneratorController::class, 'import']);
    Route::post('generator/sync', [GeneratorController::class, 'sync']);
    Route::post('generator/update', [GeneratorController::class, 'update']);
    Route::post('generator/delete', [GeneratorController::class, 'delete']);
    Route::post('generator/preview', [GeneratorController::class, 'preview']);
    Route::post('generator/generate', [GeneratorController::class, 'generate']);
    Route::get('generator/download', [GeneratorController::class, 'download']);
    Route::get('generator/models', [GeneratorController::class, 'models']);

    // 系统维护
    Route::get('system/info',        [SystemController::class, 'info']);
    Route::post('system/clearCache', [SystemController::class, 'clearCache']);

    // 操作日志
    Route::get('log/lists',  [OperationLogController::class, 'lists']);
    Route::post('log/clear', [OperationLogController::class, 'clear']);
    // 系统配置 - 网站设置
    Route::get('config/website',      [ConfigController::class, 'getWebsite']);
    Route::post('config/website/save', [ConfigController::class, 'saveWebsite']);
    Route::get('config/copyright', [ConfigController::class, 'getCopyright']);
    Route::post('config/copyright/save', [ConfigController::class, 'saveCopyright']);
    Route::get('config/agreement', [ConfigController::class, 'getAgreement']);
    Route::post('config/agreement/save', [ConfigController::class, 'saveAgreement']);
    Route::get('config/statistics', [ConfigController::class, 'getStatistics']);
    Route::post('config/statistics/save', [ConfigController::class, 'saveStatistics']);
    Route::get('config/user', [ConfigController::class, 'getUser']);
    Route::post('config/user/save', [ConfigController::class, 'saveUser']);
    Route::get('config/login', [ConfigController::class, 'getLogin']);
    Route::post('config/login/save', [ConfigController::class, 'saveLogin']);

    // 应用设置 - 首次运行生产准备清单（只读，不执行外部探测）
    Route::get('readiness/checklist', [ReadinessController::class, 'checklist']);

    // 应用设置 - 热门搜索
    Route::get('setting/hot-search/config',  [HotSearchController::class, 'getConfig']);
    Route::post('setting/hot-search/save',   [HotSearchController::class, 'setConfig']);

    // 应用设置 - 交易设置
    Route::get('setting/transaction/config',  [TransactionSettingsController::class, 'getConfig']);
    Route::post('setting/transaction/save',   [TransactionSettingsController::class, 'setConfig']);

    // 装修管理：移动端、Tabbar 与 PC 权限域严格分离
    Route::get('decoration/mobile/page/lists', [DecorationPageController::class, 'mobileLists']);
    Route::get('decoration/mobile/page/detail', [DecorationPageController::class, 'mobileDetail']);
    Route::post('decoration/mobile/page/save', [DecorationPageController::class, 'mobileSave']);
    Route::get('decoration/tabbar/detail', [DecorationTabbarController::class, 'detail']);
    Route::post('decoration/tabbar/save', [DecorationTabbarController::class, 'save']);
    Route::get('decoration/pc/page/lists', [DecorationPageController::class, 'pcLists']);
    Route::get('decoration/pc/page/detail', [DecorationPageController::class, 'pcDetail']);
    Route::post('decoration/pc/page/save', [DecorationPageController::class, 'pcSave']);

})->middleware([LoginMiddleware::class, AuthMiddleware::class, OperationLogMiddleware::class]);

// The decoration article picker needs its Module guard after the native session
// is established but before RBAC and operation logging inspect the request.
Route::group('api/admin', function (): void {
    Route::get('decoration/mobile/article', [DecorationPageController::class, 'article']);
})->middleware([
    LoginMiddleware::class,
    ArticleModuleMiddleware::class,
    AuthMiddleware::class,
    OperationLogMiddleware::class,
]);

// ═══════════════════════════════════════════════════════════════════════════════
// 用户端 API（/api/user/ 和 /api/  命名空间）
// 公开接口无中间件；需登录接口挂 CheckTokenMiddleware
// ═══════════════════════════════════════════════════════════════════════════════

// ─── 公开接口（无需 token） ────────────────────────────────────────────────────
Route::get('api/index/index',   [ApiIndexController::class, 'index'])
    ->middleware(PublicArticleTenantMiddleware::class, 'article.index');
Route::get('api/index/config',  [ApiIndexController::class, 'config'])
    ->middleware(PublicDecorationTenantMiddleware::class, 'decoration.config');
Route::get('api/index/policy',  [ApiIndexController::class, 'policy'])
    ->middleware(PublicDecorationTenantMiddleware::class, 'decoration.config');

Route::post('api/login/logout',   [ApiLoginController::class, 'logout']);
Route::get('api/storage/private', [ApiStorageController::class, 'privateFile']);

Route::get('api/article/cate',    [ApiArticleController::class, 'cate'])
    ->middleware(PublicArticleTenantMiddleware::class, 'article.cate');
Route::get('api/article/lists',   [ApiArticleController::class, 'lists'])
    ->middleware(PublicArticleTenantMiddleware::class, 'article.lists');
Route::get('api/article/detail',  [ApiArticleController::class, 'detail'])
    ->middleware(PublicArticleTenantMiddleware::class, 'article.detail');

Route::get('api/search/hotLists', [ApiSearchController::class, 'hotLists'])
    ->middleware(PublicHotSearchTenantMiddleware::class);

// 装修消费（匿名只读，保存后立即生效）
Route::get('api/decoration/mobile', [ApiDecorationController::class, 'mobilePage'])
    ->middleware(PublicDecorationTenantMiddleware::class, 'decoration.mobile-page');
Route::get('api/decoration/tabbar', [ApiDecorationController::class, 'tabbar'])
    ->middleware(PublicDecorationTenantMiddleware::class, 'decoration.config');
Route::get('api/decoration/pc', [ApiDecorationController::class, 'pcPage'])
    ->middleware(PublicDecorationTenantMiddleware::class, 'decoration.pc-page');

// PC 端聚合（公开）
Route::get('api/pc/config',         [ApiPcController::class, 'config'])
    ->middleware(PublicDecorationTenantMiddleware::class, 'decoration.config');
Route::get('api/pc/index',          [ApiPcController::class, 'index'])
    ->middleware(PublicArticleTenantMiddleware::class, 'article.pc-index');
Route::get('api/pc/infoCenter',     [ApiPcController::class, 'infoCenter'])
    ->middleware(PublicArticleTenantMiddleware::class, 'article.info-center');
Route::get('api/pc/articleDetail',  [ApiPcController::class, 'articleDetail'])
    ->middleware(PublicArticleTenantMiddleware::class, 'article.pc-detail');

// ─── 需登录接口（挂 CheckTokenMiddleware） ──────────────────────────────────
Route::group('api', function () {
    // 文章收藏
    Route::post('article/addCollect',    [ApiArticleController::class, 'addCollect']);
    Route::post('article/cancelCollect', [ApiArticleController::class, 'cancelCollect']);
    Route::get('article/collect',        [ApiArticleController::class, 'collect']);

})->middleware([CheckTokenMiddleware::class]);

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
