<?php
declare(strict_types=1);

use app\common\service\tenant\TenantCache;
use app\common\service\tenant\TenantCacheStore;
use app\common\service\tenant\TenantNamespace;
use app\common\service\tenant\TenantScope;
use app\common\service\tenant\ThinkPhpTenantCacheStore;
use think\facade\Cache;

require dirname(__DIR__, 2) . '/vendor/autoload.php';

function expectThinkPhpTenantCache(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function expectThinkPhpTenantCacheRejected(Closure $operation, string $message): void
{
    try {
        $operation();
    } catch (InvalidArgumentException|TypeError) {
        return;
    }
    throw new RuntimeException($message);
}

$app = new think\App();
$app->initialize();

$runId = bin2hex(random_bytes(8));
$logicalKey = 'mt03:thinkphp-adapter:' . $runId;
$tenantA = TenantScope::fromTrustedContext(3101, 'fixture:' . $runId . ':a');
$tenantB = TenantScope::fromTrustedContext(3202, 'fixture:' . $runId . ':b');
$physicalA = TenantNamespace::cacheKey($tenantA, $logicalKey);
$physicalB = TenantNamespace::cacheKey($tenantB, $logicalKey);
$cacheA = TenantCache::thinkPhp($tenantA);
$cacheB = TenantCache::thinkPhp($tenantB);

try {
    expectThinkPhpTenantCache($physicalA !== $physicalB, 'physical keys must differ across tenants');
    expectThinkPhpTenantCache($cacheA->set($logicalKey, 'tenant-a', 60), 'tenant A cache write failed');
    expectThinkPhpTenantCache($cacheB->set($logicalKey, 'tenant-b', 60), 'tenant B cache write failed');
    expectThinkPhpTenantCache($cacheA->get($logicalKey) === 'tenant-a', 'tenant A cache read crossed tenants');
    expectThinkPhpTenantCache($cacheB->get($logicalKey) === 'tenant-b', 'tenant B cache read crossed tenants');
    expectThinkPhpTenantCache($cacheA->delete($logicalKey), 'tenant A scoped cleanup failed');
    expectThinkPhpTenantCache($cacheA->get($logicalKey, 'missing') === 'missing', 'tenant A key survived cleanup');
    expectThinkPhpTenantCache($cacheB->get($logicalKey) === 'tenant-b', 'tenant A cleanup deleted tenant B key');

    expectThinkPhpTenantCacheRejected(
        static fn() => TenantCache::thinkPhp(null),
        'missing trusted scope was accepted'
    );
    expectThinkPhpTenantCacheRejected(
        static fn() => TenantCache::thinkPhp(['tenant_id' => 3101]),
        'payload-shaped tenant data was accepted as trusted scope'
    );

    expectThinkPhpTenantCache(
        is_subclass_of(ThinkPhpTenantCacheStore::class, TenantCacheStore::class),
        'ThinkPHP adapter must implement the minimal tenant cache store port'
    );
    $storeMethods = array_map(
        static fn(ReflectionMethod $method): string => $method->getName(),
        (new ReflectionClass(ThinkPhpTenantCacheStore::class))->getMethods(ReflectionMethod::IS_PUBLIC)
    );
    expectThinkPhpTenantCache(
        !array_intersect(['clear', 'flush', 'keys', 'store'], $storeMethods),
        'ThinkPHP adapter exposed a global or raw cache operation'
    );
} finally {
    $cacheA->delete($logicalKey);
    $cacheB->delete($logicalKey);
}

expectThinkPhpTenantCache(Cache::get($physicalA, null) === null, 'tenant A fixture key was not cleaned');
expectThinkPhpTenantCache(Cache::get($physicalB, null) === null, 'tenant B fixture key was not cleaned');

echo "MT03-THINKPHP-CACHE-ADAPTER-001 passed\n";
