<?php
declare(strict_types=1);

namespace app\command;

use app\platform\service\module\DeploymentModuleInstaller;
use PeanutAdmin\Kernel\Module\ModuleException;
use app\common\execution\DatabaseContextualCommand;
use think\console\Input;
use think\console\input\Argument;
use think\console\Output;
use think\facade\Config;

/** Registers a manifest from the explicit PEANUT_MODULE_ROOTS deployment registry. */
final class ModuleInstall extends DatabaseContextualCommand
{
    protected function configure()
    {
        $this->setName('module:install')
            ->setDescription('Register an explicitly deployed Module')
            ->addArgument('module_key', Argument::REQUIRED, 'Module key');
    }

    protected function handle(Input $input, Output $output): int
    {
        try {
            $pdo = $this->database();
            $config = Config::get('modules', []);
            if (!is_array($config)) {
                throw new ModuleException('MODULE_REGISTRY_UNAVAILABLE', 'Module deployment config is invalid.');
            }
            $identity = (new DeploymentModuleInstaller($pdo, dirname(__DIR__, 2)))->install(
                trim((string)$input->getArgument('module_key')),
                $config
            );
            $output->writeln((string)json_encode(
                [
                    'key' => $identity['key'],
                    'version' => $identity['version'],
                    'digest' => $identity['digest'],
                    'status' => $identity['status'],
                ],
                JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES
            ));
            return 0;
        } catch (ModuleException $exception) {
            $output->writeln((string)json_encode(
                ['error' => $exception->errorCode],
                JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES
            ));
            return 1;
        } catch (\Throwable) {
            $output->writeln('{"error":"MODULE_INSTALLATION_FAILED"}');
            return 1;
        }
    }
}
