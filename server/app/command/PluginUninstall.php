<?php
declare(strict_types=1);

namespace app\command;

use think\console\Command;
use think\console\Input;
use think\console\input\Argument;
use think\console\Output;

final class PluginUninstall extends Command
{
    use PluginCommandSupport;

    protected function configure()
    {
        $this->setName('plugin:uninstall')->setDescription('Retire a Plugin while preserving all data')
            ->addArgument('plugin_key', Argument::REQUIRED, 'Plugin key');
    }

    protected function execute(Input $input, Output $output)
    {
        $key = trim((string)$input->getArgument('plugin_key'));
        return $this->runPluginOperation($output, static fn($service): array => $service->uninstall($key));
    }
}
