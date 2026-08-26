<?php
declare(strict_types=1);

namespace app\command;

use app\platform\service\module\PdoModuleGovernanceProvider;
use app\platform\service\plugin\PluginLifecycleException;
use PDO;
use think\console\Output;
use think\facade\Config;
use think\facade\Db;

trait PluginCommandSupport
{
    private function pluginLifecycle(): \app\common\contract\module\PluginLifecycleCommands
    {
        $pdo = Db::connect()->connect();
        if (!$pdo instanceof PDO) {
            throw new PluginLifecycleException('PLATFORM_DATABASE_CONNECTION_UNAVAILABLE', 'Database is unavailable.');
        }
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
        } catch (\Throwable $exception) {
            $output->writeln((string)json_encode(
                [
                    'error' => 'PLUGIN_LIFECYCLE_FAILED',
                    'exception' => get_class($exception),
                    'message' => $exception->getMessage(),
                    'file' => $exception->getFile() . ':' . $exception->getLine(),
                ],
                JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES
            ));
            return 1;
        }
    }
}
