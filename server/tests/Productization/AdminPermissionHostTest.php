<?php
declare(strict_types=1);

use app\common\contract\AdminPermissionPolicy;
use app\common\service\CoreServiceOverrides;
use app\common\service\permission\RegisteredAdminPermissionPolicy;

require dirname(__DIR__, 2) . '/vendor/autoload.php';

function expectPermission(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$policy = new RegisteredAdminPermissionPolicy();
$registered = ['article/lists', 'admin/edit'];

expectPermission($policy->canAccess(true, 'article/lists', $registered, []) === true, 'root must pass');
expectPermission($policy->canAccess(false, 'health/read', $registered, []) === true, 'unregistered URI must pass');
expectPermission($policy->canAccess(false, 'article/lists', $registered, []) === false, 'registered unowned URI must fail');
expectPermission(
    $policy->canAccess(false, '/ARTICLE/LISTS/', $registered, ['ARTICLE/LISTS']) === true,
    'registered owned URI must normalize and pass'
);
expectPermission(
    $policy->canAccess(false, 'admin/status', $registered, ['admin/edit'], ['admin/status' => 'admin/edit']) === true,
    'alias must resolve before authorization'
);
expectPermission(
    $policy->canAccess(false, 'admin/status', $registered, [], ['admin/status' => 'admin/edit']) === false,
    'alias target still requires ownership'
);

$app = new think\App();
$app->initialize();
$resolution = CoreServiceOverrides::registry()->resolve(CoreServiceOverrides::ADMIN_PERMISSION_POLICY);
expectPermission($resolution->key === 'authorization.permission.service.policy', 'PHP override key must be stable');
expectPermission($resolution->contract === AdminPermissionPolicy::class, 'PHP override contract must be stable');
expectPermission($resolution->contractVersion === '1.0.0', 'PHP override version must be stable');
expectPermission($resolution->implementation === RegisteredAdminPermissionPolicy::class, 'default policy must be registered');
expectPermission($resolution->source === 'default', 'empty application override list must resolve the default');

echo "PB04-AUTH-HOST-001 PHP passed\n";
