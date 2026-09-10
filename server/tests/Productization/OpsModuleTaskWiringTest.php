<?php
declare(strict_types=1);

use app\command\OpsModuleTask;
use app\AppService;
use app\common\execution\CurrentExecutionContext;
use app\common\execution\ExecutionContextStore;
use app\common\service\audit\AuditContractHost;
use app\platform\service\ops\PlatformOpsRuntimeFactory;
use think\App;
use think\console\Input;
use think\console\Output;

require dirname(__DIR__, 2) . '/vendor/autoload.php';

function expectOpsModuleWiring(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$app = new App(dirname(__DIR__, 2));
$contexts = new ExecutionContextStore();
$current = new CurrentExecutionContext($contexts);
$pdo = new class extends PDO { public function __construct() {} };
$audit = (new ReflectionClass(AuditContractHost::class))->newInstanceWithoutConstructor();
$trustedKey = str_repeat('k', SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES);
$app->config->set(['official.article' => ['root' => 'app/Modules/Official/Article']], 'modules');
$app->config->set(['trusted_ed25519_keys' => ['c01-test' => base64_encode($trustedKey)]], 'module_packages');
$app->instance(ExecutionContextStore::class, $contexts);
$app->instance(CurrentExecutionContext::class, $current);
$app->instance(PDO::class, $pdo);
$app->instance(AuditContractHost::class, $audit);
$appService = new AppService($app);
$registerPlatform = new ReflectionMethod(AppService::class, 'registerPlatform');
$registerPlatform->invoke($appService);

$registeredFactory = $app->make(PlatformOpsRuntimeFactory::class);
$command = $app->make(OpsModuleTask::class);
$runtimeProperty = new ReflectionProperty(OpsModuleTask::class, 'runtime');
expectOpsModuleWiring(
    $runtimeProperty->getValue($command) === $registeredFactory
        && $registeredFactory === $app->make(PlatformOpsRuntimeFactory::class),
    'OpsModuleTask did not receive the AppService-owned PlatformOpsRuntimeFactory instance',
);
$moduleConfigProperty = new ReflectionProperty(PlatformOpsRuntimeFactory::class, 'moduleConfig');
expectOpsModuleWiring(
    $moduleConfigProperty->getValue($registeredFactory) === ['official.article' => ['root' => 'app/Modules/Official/Article']],
    'AppService did not provide Module configuration to the Ops Runtime factory',
);
$trustedKeysProperty = new ReflectionProperty(PlatformOpsRuntimeFactory::class, 'trustedKeys');
expectOpsModuleWiring(
    $trustedKeysProperty->getValue($registeredFactory) === ['c01-test' => $trustedKey],
    'AppService did not decode trusted Module keys for the Ops Runtime factory',
);
$source = (string)file_get_contents(dirname(__DIR__, 2) . '/app/command/OpsModuleTask.php');
expectOpsModuleWiring(!str_contains($source, 'new PlatformOpsRuntimeFactory'), 'OpsModuleTask retained duplicate Runtime factory construction');
expectOpsModuleWiring(!str_contains($source, 'trustedKeys('), 'OpsModuleTask retained duplicate trusted-key decoding');

$command->setApp($app);
$invalidKeyOutput = new Output('buffer');
expectOpsModuleWiring(
    $command->run(new Input(['advance']), $invalidKeyOutput) === 1
        && str_contains($invalidKeyOutput->fetch(), 'OPS_MODULE_TASK_KEY_INVALID'),
    'OpsModuleTask no longer rejects a missing task key before execution',
);
$invalidRevisionOutput = new Output('buffer');
expectOpsModuleWiring(
    $command->run(new Input(['advance', '--task-key=job_' . str_repeat('a', 32), '--revision=0']), $invalidRevisionOutput) === 1
        && str_contains($invalidRevisionOutput->fetch(), 'OPS_MODULE_EXECUTION_REVISION_INVALID'),
    'OpsModuleTask no longer rejects an invalid revision before execution',
);

echo "C01-C-OPS-MODULE-WIRING passed\n";
