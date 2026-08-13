<?php
declare(strict_types=1);

use app\common\contract\AdminPermissionPolicy;
use app\common\service\CoreServiceOverrides;
use app\common\service\permission\RegisteredAdminPermissionPolicy;
use PeanutAdmin\Kernel\Override\ServiceOverrideRegistry;

require dirname(__DIR__, 2) . '/vendor/autoload.php';

$expect = static function (bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
};

$app = new think\App();
$app->initialize();

$expect(class_exists(ServiceOverrideRegistry::class), 'published Kernel namespace is not autoloadable');
$resolution = CoreServiceOverrides::registry()->resolve(CoreServiceOverrides::ADMIN_PERMISSION_POLICY);
$expect($resolution->contract === AdminPermissionPolicy::class, 'application Host contract changed');
$expect($resolution->implementation === RegisteredAdminPermissionPolicy::class, 'application Host default changed');
$expect($resolution->source === 'default', 'application Host unexpectedly selected an override');

echo "CORE-UPGRADE-PHP-HOST-001 passed\n";
