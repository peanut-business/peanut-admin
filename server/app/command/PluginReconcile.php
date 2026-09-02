<?php
declare(strict_types=1);

namespace app\command;

use app\platform\service\plugin\PluginLifecycleException;
use app\platform\service\plugin\PluginLockResolver;
use app\common\execution\DatabaseContextualCommand;
use think\console\Input;
use think\console\input\Option;
use think\console\Output;
use think\facade\Config;

final class PluginReconcile extends DatabaseContextualCommand
{
    use PluginCommandSupport;

    protected function configure()
    {
        $this->setName('plugin:reconcile')
            ->setDescription('Reconcile immutable locked Plugin installations')
            ->addOption('official-locked', null, Option::VALUE_NONE, 'Reconcile every official.* Plugin in plugins.lock');
    }

    protected function handle(Input $input, Output $output): int
    {
        if (!(bool)$input->getOption('official-locked')) {
            $output->writeln('{"error":"OFFICIAL_LOCKED_REQUIRED"}');
            return 1;
        }

        return $this->runPluginOperation(
            $output,
            function ($service): array {
                $config = Config::get('modules', []);
                $lockPath = is_array($config) ? trim((string)($config['plugin_lock'] ?? '')) : '';
                if ($lockPath === '') {
                    throw new PluginLifecycleException('PLUGIN_LOCK_INVALID', 'Plugin lock path is not configured.');
                }
                $resolver = new PluginLockResolver(dirname(__DIR__, 2), $lockPath);
                $keys = array_values(array_filter(
                    array_keys($resolver->all()),
                    static fn(string $key): bool => str_starts_with($key, 'official.')
                ));
                sort($keys, SORT_STRING);
                if ($keys === []) {
                    throw new PluginLifecycleException(
                        'OFFICIAL_PLUGIN_SET_EMPTY',
                        'plugins.lock contains no official Plugin.'
                    );
                }

                $results = ['installed' => [], 'upgraded' => [], 'unchanged' => []];
                foreach ($keys as $key) {
                    $result = $service->reconcile($key);
                    $operation = (string)($result['operation'] ?? '');
                    if (!array_key_exists($operation, $results)) {
                        throw new PluginLifecycleException(
                            'PLUGIN_RECONCILE_RESULT_INVALID',
                            "Plugin reconciliation returned an unsupported operation: {$operation}"
                        );
                    }
                    $results[$operation][] = $result;
                }

                return $results;
            }
        );
    }
}
