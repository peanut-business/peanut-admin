<?php
declare(strict_types=1);

use app\common\service\instance\InstanceToolAccessGuard;

$serverRoot = dirname(__DIR__, 2);
require $serverRoot . '/app/common/service/instance/DeploymentMode.php';
require $serverRoot . '/app/common/service/instance/InstanceToolAccessGuard.php';

function instanceToolExpect(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

instanceToolExpect(
    InstanceToolAccessGuard::fromConfiguredValue('standalone')->allows(),
    'explicit standalone mode must preserve instance tool access'
);
foreach ([null, '', 'multi-tenant', 'production', 'STANDALONE', 1] as $value) {
    instanceToolExpect(
        !InstanceToolAccessGuard::fromConfiguredValue($value)->allows(),
        'missing, multi-tenant, or unknown deployment mode must fail closed'
    );
}

$config = (string)file_get_contents($serverRoot . '/config/deployment.php');
instanceToolExpect(
    str_contains($config, "env('DEPLOYMENT_MODE')")
        && !str_contains($config, "env('DEPLOYMENT_MODE', 'standalone')"),
    'server deployment mode must not silently default to standalone'
);

$generator = (string)file_get_contents($serverRoot . '/app/adminapi/controller/generator/GeneratorController.php');
$actions = ['sourceTables', 'lists', 'detail', 'import', 'sync', 'update', 'delete', 'preview', 'generate', 'download', 'models'];
foreach ($actions as $action) {
    $pattern = sprintf(
        '/public function %s\(\)\s*\{\s*\$denial = \$this->instanceToolAccessDenial\(\);\s*if \(\$denial !== null\) return \$denial;/s',
        preg_quote($action, '/')
    );
    instanceToolExpect(
        preg_match($pattern, $generator) === 1,
        "generator {$action} must deny before request validation or any instance side effect"
    );
}

$system = (string)file_get_contents($serverRoot . '/app/adminapi/controller/system/SystemController.php');
foreach (['info' => 'SystemApplicationService::getInfo', 'clearCache' => 'SystemApplicationService::clearCache'] as $action => $effect) {
    instanceToolExpect(
        preg_match(
            '/public function ' . $action . '\(\)\s*\{\s*\$denial = \$this->instanceToolAccessDenial\(\);\s*if \(\$denial !== null\).*?return \$denial;.*?' . preg_quote($effect, '/') . '\(/s',
            $system
        ) === 1,
        "system {$action} must deny before instance information or side effects"
    );
}
$menu = (string)file_get_contents($serverRoot . '/app/adminapi/controller/auth/MenuController.php');
foreach (['lists', 'detail', 'add', 'edit', 'delete', 'updateStatus'] as $action) {
    instanceToolExpect(
        preg_match(
            '/public function ' . $action . '\(\).*?\$this->instanceMenuDenial\(\)/s',
            $menu
        ) === 1,
        "global menu action {$action} is still exposed in multi-tenant mode"
    );
}
instanceToolExpect(
    substr_count($generator, "ApiProblem::fromEnvelope('实例级开发工具仅在 standalone 部署中可用', null, 40300)") === 1
        && substr_count($system, "ApiProblem::fromEnvelope('实例级维护工具仅在 standalone 部署中可用', null, 40300)") === 1,
    'instance tool denial must retain a stable forbidden response'
);

echo "MT04-INSTANCE-TOOL-ACCESS-BOUNDARY-001 passed\n";
