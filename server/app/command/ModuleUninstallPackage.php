<?php
declare(strict_types=1);

namespace app\command;

use app\common\service\instance\InstanceToolAccessGuard;
use app\platform\service\plugin\PluginLifecycleException;
use app\platform\service\plugin\PluginRuntimeGovernanceService;
use app\common\execution\ContextualCommand;
use think\console\Input;
use think\console\input\Argument;
use think\console\input\Option;
use think\console\Output;
use think\facade\Config;

final class ModuleUninstallPackage extends ContextualCommand
{
    protected function configure()
    {
        $this->setName('module:uninstall-package')->setDescription('Preview or execute a recoverable Module package retire/purge')
            ->addArgument('module_key', Argument::REQUIRED, 'Module key or package key')
            ->addOption('purge', null, Option::VALUE_NONE, 'Delete owned tables, migration ledger, catalog and explicit RBAC bindings')
            ->addOption('confirm-plan-file', null, Option::VALUE_REQUIRED, 'JSON file containing the exact preview confirm_plan', '')
            ->addOption('confirm-plan-digest', null, Option::VALUE_REQUIRED, 'SHA-256 returned by preview', '');
    }

    protected function handle(Input $input, Output $output): int
    {
        try {
            if (strtolower(trim((string)env('APP_ENV', ''))) !== 'development'
                || !app()->isDebug()
                || !InstanceToolAccessGuard::fromConfiguredValue(Config::get('deployment.mode'))->allows()) {
                throw new PluginLifecycleException('MODULE_RUNTIME_MUTATION_DISABLED', 'Runtime Module mutation is disabled.');
            }
            $pdo = $this->database();
            $config = Config::get('modules', []);
            if (!is_array($config)) throw new PluginLifecycleException('MODULE_REGISTRY_UNAVAILABLE', 'Module deployment config is invalid.');
            $service = new PluginRuntimeGovernanceService($pdo, dirname(__DIR__, 2), $config);
            $key = trim((string)$input->getArgument('module_key'));
            $purge = (bool)$input->getOption('purge');
            $planFile = trim((string)$input->getOption('confirm-plan-file'));
            $digest = trim((string)$input->getOption('confirm-plan-digest'));
            if ($planFile === '' && $digest === '') {
                $result = $service->preview($key, $purge);
            } else {
                if ($planFile === '' || $digest === '' || !is_file($planFile)) throw new PluginLifecycleException('MODULE_UNINSTALL_PLAN_CHANGED', 'Both confirmation options are required.');
                $plan = json_decode((string)file_get_contents($planFile), true, 128, JSON_THROW_ON_ERROR);
                if (!is_array($plan) || array_is_list($plan)) throw new PluginLifecycleException('MODULE_UNINSTALL_PLAN_CHANGED', 'Confirmed plan file is invalid.');
                $result = $service->uninstall($key, $purge, $plan, $digest);
            }
            $output->writeln((string)json_encode($result, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
            return 0;
        } catch (PluginLifecycleException $exception) {
            $output->writeln((string)json_encode(['error' => $exception->errorCode], JSON_THROW_ON_ERROR));
            return 1;
        } catch (\Throwable) {
            $output->writeln('{"error":"MODULE_UNINSTALL_FAILED"}');
            return 1;
        }
    }
}
