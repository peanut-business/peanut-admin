<?php
declare(strict_types=1);

use app\common\contract\AdminPermissionPolicy;
use app\adminapi\service\AdminApiAccessRegistry;
use app\common\service\CoreServiceOverrides;
use app\common\service\permission\RegisteredAdminPermissionPolicy;

require dirname(__DIR__, 2) . '/bootstrap/environment.php';
require dirname(__DIR__, 2) . '/vendor/autoload.php';

function expectPermission(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$policy = new RegisteredAdminPermissionPolicy();
$registered = ['article/lists', 'admin/edit'];

expectPermission($policy->canAccess(true, 'article/lists', $registered, []) === true, 'root must pass registered permission');
expectPermission($policy->canAccess(true, 'health/read', $registered, []) === false, 'root must not bypass registration');
expectPermission($policy->canAccess(false, 'health/read', $registered, []) === false, 'unregistered URI must fail');
expectPermission($policy->canAccess(false, 'article/lists', $registered, []) === false, 'registered unowned URI must fail');
expectPermission(
    $policy->canAccess(false, '/ARTICLE/LISTS/', $registered, ['ARTICLE/LISTS']) === true,
    'registered owned URI must normalize and pass'
);
expectPermission($policy->canAccess(false, 'admin/status', ['admin/edit'], ['admin/edit']) === false, 'URI aliases must not enlarge authorization');

$app = new think\App();
$app->initialize();
expectPermission(AdminApiAccessRegistry::version() === 1, 'admin exception metadata version must be fixed');
expectPermission(AdminApiAccessRegistry::isAuthenticatedOnly('GET', 'api/admin/admin/self'), 'self endpoint must be authenticated-only');
expectPermission(!AdminApiAccessRegistry::isAuthenticatedOnly('POST', 'api/admin/admin/self'), 'authenticated metadata must be method-specific');
expectPermission(!AdminApiAccessRegistry::isAuthenticatedOnly('GET', 'api/admin/official.article.list'), 'business endpoint must not bypass RBAC');
expectPermission(AdminApiAccessRegistry::isPublic('POST', 'api/user/login'), 'public login endpoint must be explicit');
expectPermission(!AdminApiAccessRegistry::isPublic('GET', 'api/user/login'), 'public metadata must be method-specific');
expectPermission(AdminApiAccessRegistry::isPublic('POST', 'api/tenant/session/select'), 'Tenant selection endpoint must be explicit');
expectPermission(AdminApiAccessRegistry::isPlatformPublic('POST', 'api/platform/session/login'), 'Platform login must use its own metadata');
expectPermission(AdminApiAccessRegistry::isPlatformAuthenticatedOnly('GET', 'api/platform/session/info'), 'Platform session info must be authenticated-only');
expectPermission(!AdminApiAccessRegistry::isPublic('POST', 'api/platform/session/login'), 'Platform route must not enter Tenant Admin metadata');

$resolution = CoreServiceOverrides::registry()->resolve(CoreServiceOverrides::ADMIN_PERMISSION_POLICY);
expectPermission($resolution->key === 'authorization.permission.service.policy', 'PHP override key must be stable');
expectPermission($resolution->contract === AdminPermissionPolicy::class, 'PHP override contract must be stable');
expectPermission($resolution->contractVersion === '2.0.0', 'PHP override version must be stable');
expectPermission($resolution->implementation === RegisteredAdminPermissionPolicy::class, 'default policy must be registered');
expectPermission($resolution->source === 'default', 'empty application override list must resolve the default');

echo "PB04-AUTH-HOST-001 PHP passed\n";
