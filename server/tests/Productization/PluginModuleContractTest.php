<?php
declare(strict_types=1);

use app\platform\service\module\OpisManifestSchemaValidator;
use app\platform\service\module\ReflectionContractInspector;
use app\platform\service\module\StrictVersionConstraintMatcher;
use PeanutAdmin\DataPermission\Persistence\Schema\DataPermissionSchema;
use PeanutAdmin\Kernel\Authorization\Persistence\Schema\AuthorizationSchema;
use PeanutAdmin\Kernel\Idempotency\IdempotencySchema;
use PeanutAdmin\Kernel\Migration\ModuleSchema;
use PeanutAdmin\Kernel\Module\ManifestDocument;
use PeanutAdmin\Kernel\Module\ManifestLoader;
use PeanutAdmin\Kernel\Module\ModuleBoundaryChecker;
use PeanutAdmin\Kernel\Module\ModuleException;
use PeanutAdmin\Kernel\Module\ModuleHostLayout;
use PeanutAdmin\Kernel\Module\ModuleRegistryCompiler;
use PeanutAdmin\Kernel\Persistence\Schema\KernelSchema;

require dirname(__DIR__, 2) . '/vendor/autoload.php';
require dirname(__DIR__, 2) . '/app/Modules/Fixture/DeliveryRecord/Contracts/DeliveryRecordCommands.php';
require dirname(__DIR__, 2) . '/app/Modules/Fixture/DeliveryRecord/Application/DeliveryRecordAccess.php';
require dirname(__DIR__, 2) . '/app/Modules/Fixture/DeliveryRecord/Infrastructure/Persistence/PdoDeliveryRecordRepository.php';
require dirname(__DIR__, 2) . '/app/Modules/Fixture/DeliveryRecord/Infrastructure/Authorization/PdoDeliveryRecordAccess.php';
require dirname(__DIR__, 2) . '/app/Modules/Fixture/DeliveryRecord/Application/DeliveryRecordService.php';
require dirname(__DIR__, 2) . '/app/Modules/Fixture/DeliveryRecord/ModuleProvider.php';
function pluginModuleContractExpect(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$serverRoot = dirname(__DIR__, 2);
$moduleRoot = $serverRoot . '/app/Modules/Fixture/DeliveryRecord';
$officialModuleRoots = glob($serverRoot . '/app/Modules/Official/*', GLOB_ONLYDIR) ?: [];
sort($officialModuleRoots, SORT_STRING);
$moduleRoots = [$moduleRoot, ...$officialModuleRoots];
$layout = new ModuleHostLayout('server/app/Modules', 'app\Modules', 'web/src/modules');
$kernelRoot = dirname((new ReflectionClass(\PeanutAdmin\Kernel\Module\ModuleProvider::class))->getFileName(), 3);
$compiler = new ModuleRegistryCompiler(
    new OpisManifestSchemaValidator($kernelRoot . '/resources/schemas/module-manifest.schema.json'),
    new StrictVersionConstraintMatcher(),
    new ReflectionContractInspector(),
    '1.0.0',
    [
        'fixture.delivery-record.list',
        'official.article.cate', 'official.article.list',
        'official.file.library',
        'official.notification.channel', 'official.notification.template', 'official.notification.log',
        'official.oauth.channel',
        'official.payment.settings', 'official.payment.recharge', 'official.payment.refund',
        'official.member.list', 'official.member.tag', 'official.member.account-log',
        'official.task.schedules',
    ],
    $layout,
    [
        ...KernelSchema::tableNames(),
        ...AuthorizationSchema::tableNames(),
        ...ModuleSchema::tableNames(),
        ...IdempotencySchema::tableNames(),
        ...DataPermissionSchema::tableNames(),
    ],
    ['admin-web', 'platform-web']
);

$loader = new ManifestLoader();
$manifests = array_map(static fn(string $root) => $loader->load($root), $moduleRoots);
$manifest = $manifests[0];
$registry = $compiler->compile($manifests);
$expectedKeys = array_map(
    static fn(ManifestDocument $manifest): string => (string)$manifest->data['key'],
    $manifests,
);
$actualKeys = $registry->moduleKeys();
sort($expectedKeys, SORT_STRING);
sort($actualKeys, SORT_STRING);
pluginModuleContractExpect(
    $actualKeys === $expectedKeys,
    'source official Modules did not compile together'
);
(new ModuleBoundaryChecker($registry, $layout, ['pa_']))->check();

$missingDependency = $manifests[0]->data;
$missingDependency['dependencies'] = [['module_key' => 'fixture.missing', 'version' => '^1.0']];
try {
    $compiler->compile([ManifestDocument::fromArray($manifest->root, $missingDependency)]);
    throw new RuntimeException('missing Module dependency was accepted');
} catch (ModuleException $exception) {
    pluginModuleContractExpect(
        $exception->errorCode === 'MODULE_DEPENDENCY_MISSING',
        "missing dependency rejection changed: {$exception->errorCode}"
    );
}

echo "PLUGIN-MODULE-CONTRACT-001 passed\n";
