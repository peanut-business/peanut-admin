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
$layout = new ModuleHostLayout('server/app/Modules', 'app\Modules', 'web/src/modules');
$kernelRoot = dirname((new ReflectionClass(\PeanutAdmin\Kernel\Module\ModuleProvider::class))->getFileName(), 3);
$compiler = new ModuleRegistryCompiler(
    new OpisManifestSchemaValidator($kernelRoot . '/resources/schemas/module-manifest.schema.json'),
    new StrictVersionConstraintMatcher(),
    new ReflectionContractInspector(),
    '1.0.0',
    ['fixture.delivery-record.list'],
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

$manifest = (new ManifestLoader())->load($moduleRoot);
$registry = $compiler->compile([$manifest]);
pluginModuleContractExpect($registry->moduleKeys() === ['fixture.delivery-record'], 'fixture Module did not compile');
pluginModuleContractExpect(
    str_contains(
        (string)file_get_contents(dirname(__DIR__, 2) . '/app/platform/service/plugin/PluginLifecycleService.php'),
        '$resolvedDirectories[$resolved] = true'
    ),
    'Plugin migration discovery does not deduplicate manifest and conventional directories by real path'
);
(new ModuleBoundaryChecker($registry, $layout, ['pa_']))->check();

$missingDependency = $manifest->data;
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
