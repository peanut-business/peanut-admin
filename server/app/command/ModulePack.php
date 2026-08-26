<?php
declare(strict_types=1);

namespace app\command;

use app\platform\service\plugin\PluginPackageArchiveService;
use think\console\Command;
use think\console\Input;
use think\console\input\Argument;
use think\console\input\Option;
use think\console\Output;

final class ModulePack extends Command
{
    use ModulePackageCommandSupport;

    protected function configure()
    {
        $this->setName('module:pack')->setDescription('Build one deterministic self-contained Module tar package')
            ->addArgument('module_key', Argument::REQUIRED, 'Module key')
            ->addOption('output', null, Option::VALUE_REQUIRED, 'Project-relative or absolute output tar path', '')
            ->addOption('signing-key-id', null, Option::VALUE_REQUIRED, 'Trusted Ed25519 signing key id', '')
            ->addOption('signing-secret-key-file', null, Option::VALUE_REQUIRED, 'Base64 Ed25519 secret key file', '');
    }

    protected function execute(Input $input, Output $output)
    {
        return $this->runPackageCommand($output, function () use ($input): array {
            $key = trim((string)$input->getArgument('module_key'));
            $service = new PluginPackageArchiveService(dirname(__DIR__, 2));
            $inspection = (new \app\platform\service\plugin\ModulePackagePreflight(dirname(__DIR__, 3)))->inspect($key);
            return $service->packModule(
                $key,
                $this->packageOutput($key, $inspection['version'], trim((string)$input->getOption('output'))),
                $this->packageSigner($input),
            );
        });
    }
}
