<?php
declare(strict_types=1);

namespace app\command;

use app\common\execution\ContextualCommand;
use think\console\Input;
use think\console\input\Argument;
use think\console\Output;

final class PluginRollback extends ContextualCommand
{
    use PluginCommandSupport;

    protected function configure()
    {
        $this->setName('plugin:rollback')->setDescription('Output a preserve-data Plugin rollback plan')
            ->addArgument('plugin_key', Argument::REQUIRED, 'Plugin key');
    }

    protected function handle(Input $input, Output $output): int
    {
        $key = trim((string)$input->getArgument('plugin_key'));
        return $this->runPluginOperation($output, static fn($service): array => $service->rollbackPlan($key));
    }
}
