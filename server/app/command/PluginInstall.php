<?php
declare(strict_types=1);

namespace app\command;

use think\console\Command;
use think\console\Input;
use think\console\input\Argument;
use think\console\Output;

final class PluginInstall extends Command
{
    use PluginCommandSupport;

    protected function configure()
    {
        $this->setName('plugin:install')->setDescription('Install one immutable locked Plugin')
            ->addArgument('plugin_key', Argument::REQUIRED, 'Plugin key');
    }

    protected function execute(Input $input, Output $output)
    {
        $key = trim((string)$input->getArgument('plugin_key'));
        return $this->runPluginOperation($output, static fn($service): array => $service->install($key));
    }
}
