<?php
declare(strict_types=1);

namespace app\command;

use app\platform\service\plugin\PluginArtifactToolException;
use app\platform\service\plugin\PluginArtifactWriter;
use think\console\Command;
use think\console\Input;
use think\console\input\Option;
use think\console\Output;

final class PluginLock extends Command
{
    protected function configure()
    {
        $this->setName('plugin:lock')->setDescription('Write or verify the canonical Plugin lock')
            ->addOption('write', null, Option::VALUE_NONE, 'Write plugins.lock')
            ->addOption('check', null, Option::VALUE_NONE, 'Check plugins.lock without writing');
    }

    protected function execute(Input $input, Output $output)
    {
        try {
            $write = (bool)$input->getOption('write');
            $check = (bool)$input->getOption('check');
            if ($write === $check) throw new PluginArtifactToolException('Specify exactly one of --write or --check.');
            $writer = new PluginArtifactWriter(dirname(__DIR__, 2));
            $result = $write ? $writer->writeLock() : $writer->checkLock();
            $output->writeln((string)json_encode(['status' => $write ? 'written' : 'valid'] + $result, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
            return 0;
        } catch (PluginArtifactToolException $exception) {
            $output->writeln((string)json_encode(['error' => $exception->getMessage()], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
            return 1;
        }
    }
}
