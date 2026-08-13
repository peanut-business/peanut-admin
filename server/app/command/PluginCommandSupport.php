<?php
declare(strict_types=1);

namespace app\command;

use app\platform\service\plugin\PluginLifecycleException;
use app\platform\service\plugin\PluginLifecycleService;
use app\platform\service\plugin\PluginLockResolver;
use app\platform\service\plugin\PluginModuleRegistryFactory;
use PDO;
use think\console\Output;
use think\facade\Config;
use think\facade\Db;

trait PluginCommandSupport
{
    private function pluginLifecycle(): PluginLifecycleService
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
        $lockPath = trim((string)($config['plugin_lock'] ?? ''));
        if ($lockPath === '') {
            throw new PluginLifecycleException('PLUGIN_LOCK_INVALID', 'Plugin lock path is not configured.');
        }
        $resolver = new PluginLockResolver($serverRoot, $lockPath);
        return new PluginLifecycleService(
            $pdo,
            $resolver,
            new PluginModuleRegistryFactory($pdo, $serverRoot),
            $config
        );
    }

    /** @param callable(PluginLifecycleService):array<string,mixed> $operation */
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
