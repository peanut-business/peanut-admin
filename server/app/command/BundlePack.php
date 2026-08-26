<?php
declare(strict_types=1);

namespace app\command;

use app\platform\service\plugin\PluginPackageArchiveService;
use think\console\Command;
use think\console\Input;
use think\console\input\Argument;
use think\console\input\Option;
use think\console\Output;

final class BundlePack extends Command
{
    use ModulePackageCommandSupport;

    protected function configure()
    {
        $this->setName('bundle:pack')->setDescription('Build one deterministic self-contained multi-Module tar package')
            ->addArgument('bundle_key', Argument::REQUIRED, 'Bundle key')
            ->addArgument('version', Argument::REQUIRED, 'Bundle version')
            ->addArgument('module_keys', Argument::REQUIRED | Argument::IS_ARRAY, 'Two or more Module keys')
            ->addOption('output', null, Option::VALUE_REQUIRED, 'Project-relative or absolute output tar path', '')
            ->addOption('signing-key-id', null, Option::VALUE_REQUIRED, 'Trusted Ed25519 signing key id', '')
            ->addOption('signing-secret-key-file', null, Option::VALUE_REQUIRED, 'Base64 Ed25519 secret key file', '');
    }

    protected function execute(Input $input, Output $output)
    {
        return $this->runPackageCommand($output, function () use ($input): array {
            $key = trim((string)$input->getArgument('bundle_key'));
            $version = trim((string)$input->getArgument('version'));
            return (new PluginPackageArchiveService(dirname(__DIR__, 2)))->packBundle(
                $key,
                $version,
                array_values((array)$input->getArgument('module_keys')),
                $this->packageOutput($key, $version, trim((string)$input->getOption('output'))),
                $this->packageSigner($input),
            );
        });
    }
}
