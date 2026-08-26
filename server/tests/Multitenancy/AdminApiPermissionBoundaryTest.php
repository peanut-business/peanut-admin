<?php
declare(strict_types=1);

use app\adminapi\http\middleware\AuthMiddleware;
use app\common\service\permission\RegisteredAdminPermissionPolicy;

require dirname(__DIR__, 2) . '/bootstrap/environment.php';
require dirname(__DIR__, 2) . '/vendor/autoload.php';

function expectAdminApiBoundary(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function responsePayload(mixed $response): array
{
    $payload = json_decode((string)$response->getContent(), true);
    if (!is_array($payload)) {
        throw new RuntimeException('middleware response is not JSON');
    }
    return $payload;
}

$app = new think\App();
$app->initialize();

$policy = new RegisteredAdminPermissionPolicy();
$registered = ['official.article.list', 'admin/status'];
expectAdminApiBoundary($policy->canAccess(false, 'official.article.list', $registered, ['official.article.list']), 'registered grant must pass');
expectAdminApiBoundary(!$policy->canAccess(false, 'official.article.list', $registered, []), 'registered unowned route must fail');
expectAdminApiBoundary(!$policy->canAccess(false, 'unknown/detail', $registered, ['unknown/detail']), 'unregistered route must fail even if claimed as granted');
expectAdminApiBoundary($policy->canAccess(true, 'admin/status', $registered, []), 'root must bypass role ownership for a registered route');
expectAdminApiBoundary(!$policy->canAccess(true, 'admin/edit', $registered, []), 'root must not bypass route registration');
expectAdminApiBoundary(!$policy->canAccess(false, 'admin/status', ['admin/edit'], ['admin/edit']), 'status route must not inherit through a runtime alias');

$middleware = new AuthMiddleware();
$nextCalls = 0;
$next = static function ($request) use (&$nextCalls): string {
    $nextCalls++;
    return 'allowed';
};

$anonymous = new class {
    public function pathinfo(): string { return 'api/admin/admin/self'; }
    public function method(): string { return 'GET'; }
};
$anonymousDenial = responsePayload($middleware->handle($anonymous, $next));
expectAdminApiBoundary($anonymousDenial === ['code' => 40100, 'msg' => '请先登录', 'data' => null], 'anonymous denial shape changed');

$authenticated = new class {
    public array $adminInfo = ['id' => 7, 'tenant_id' => 101, 'root' => 0];
    public object $tenantContext;
    public function __construct() { $this->tenantContext = new stdClass(); }
    public function pathinfo(): string { return 'api/admin/admin/self'; }
    public function method(): string { return 'GET'; }
};
expectAdminApiBoundary($middleware->handle($authenticated, $next) === 'allowed', 'authenticated-only route must pass after login');
expectAdminApiBoundary($nextCalls === 1, 'authenticated-only route did not reach its controller exactly once');

$authenticatedWrongMethod = new class {
    public array $adminInfo = ['id' => 7, 'tenant_id' => 101, 'root' => 0];
    public object $tenantContext;
    public function __construct() { $this->tenantContext = new stdClass(); }
    public function pathinfo(): string { return 'api/admin/admin/self'; }
    public function method(): string { return 'POST'; }
};
$wrongMethodDenial = responsePayload($middleware->handle($authenticatedWrongMethod, $next));
expectAdminApiBoundary($wrongMethodDenial === ['code' => 40300, 'msg' => '暂无访问权限', 'data' => null], 'wrong-method denial must keep the generic permission shape');

$routeSource = (string)file_get_contents(dirname(__DIR__, 2) . '/route/app.php');
expectAdminApiBoundary(str_contains($routeSource, "Route::group('api/admin'"), 'Tenant Admin route group is missing');
expectAdminApiBoundary(str_contains($routeSource, 'LoginMiddleware::class, AuthMiddleware::class'), 'Tenant Admin guard chain is missing');
expectAdminApiBoundary(!str_contains($routeSource, "Route::group('api/platform'"), 'Platform routes must remain individually guarded');

$loginSource = (string)file_get_contents(dirname(__DIR__, 2) . '/app/adminapi/http/middleware/LoginMiddleware.php');
expectAdminApiBoundary(str_contains($loginSource, "str_starts_with(\$token, 'pa_tat_')"), 'Tenant Admin token audience gate is missing');
expectAdminApiBoundary(!str_contains($loginSource, 'pa_pat_'), 'Platform credential prefix must not enter Tenant Admin login middleware');

$platformLoginSource = (string)file_get_contents(dirname(__DIR__, 2) . '/app/platform/http/middleware/PlatformLoginMiddleware.php');
$platformPermissionSource = (string)file_get_contents(dirname(__DIR__, 2) . '/app/platform/http/middleware/PlatformPermissionMiddleware.php');
expectAdminApiBoundary(str_contains($platformPermissionSource, "str_starts_with(\$permission, 'platform.')"), 'Platform permission catalog boundary is missing');
expectAdminApiBoundary(!str_contains($platformLoginSource, 'AdminTokenService'), 'Platform login must not use Tenant Admin sessions');

$schema = strtolower((string)file_get_contents(dirname(__DIR__, 2) . '/database/init.sql'));
$officialPermissions = $schema
    . strtolower((string)file_get_contents(dirname(__DIR__, 2) . '/app/Modules/Official/Payment/Resources/permissions.json'))
    . strtolower((string)file_get_contents(dirname(__DIR__, 2) . '/app/Modules/Official/ImportExport/Resources/permissions.json'));
foreach (['admin/status', 'official.payment.recharge.refund', 'official.payment.refund.stat', 'official.import-export.operation.status'] as $exactPermission) {
    expectAdminApiBoundary(str_contains($officialPermissions, $exactPermission), 'exact status permission is missing: ' . $exactPermission);
}
expectAdminApiBoundary(str_contains($schema, 'insert into `pa_permission`'), 'fresh schema must seed the Core permission catalog');
expectAdminApiBoundary(str_contains($schema, 'insert ignore into `pa_role_permission`'), 'fresh schema must grant permissions through Core RBAC');
expectAdminApiBoundary(!str_contains($schema, 'pa_system_role_menu'), 'retired role-menu storage remains in the fresh schema');

echo "ADMIN-API-PERMISSION-BOUNDARY-001 passed\n";
