<?php
declare(strict_types=1);

namespace app\command;

use app\platform\service\module\PdoModuleGovernanceProvider;
use app\platform\service\plugin\PluginLifecycleException;
use PDO;
use think\console\Output;
use think\facade\Config;

trait PluginCommandSupport
{
    private function pluginLifecycle(): \app\common\contract\module\PluginLifecycleCommands
    {
        $pdo = $this->database();
        $serverRoot = dirname(__DIR__, 2);
        $config = Config::get('modules', []);
        if (!is_array($config)) {
            throw new PluginLifecycleException('MODULE_REGISTRY_UNAVAILABLE', 'Module deployment config is invalid.');
        }
        return (new PdoModuleGovernanceProvider($pdo, $serverRoot, $config))->pluginLifecycle();
    }

    /** @param callable(\app\common\contract\module\PluginLifecycleCommands):array<string,mixed> $operation */
    private function runPluginOperation(Output $output, callable $operation): int
    {
        try {
            $output->writeln((string)json_encode(
                $operation($this->pluginLifecycle()),
                JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES
            ));
            return 0;
        } catch (PluginLifecycleException $exception) {
            $output->writeln((string)json_encode(
                ['error' => $exception->errorCode],
                JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES
            ));
            return 1;
        } catch (\Throwable) {
            $output->writeln('{"error":"PLUGIN_LIFECYCLE_FAILED"}');
            return 1;
        }
    }
}
