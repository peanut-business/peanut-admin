<?php
declare(strict_types=1);

namespace app\command;

use app\common\execution\CurrentExecutionContext;
use app\common\execution\ExecutionContextStore;
use app\platform\service\ops\PlatformOpsRuntimeFactory;
use app\common\execution\DatabaseContextualCommand;
use PDO;
use think\console\Input;
use think\console\input\Argument;
use think\console\input\Option;
use think\console\Output;
use Throwable;

/** Deployment-control bridge for one fixed Module operation task. */
final class OpsModuleTask extends DatabaseContextualCommand
{
    public function __construct(
        ExecutionContextStore $contexts,
        CurrentExecutionContext $executionContext,
        PDO $pdo,
        private readonly PlatformOpsRuntimeFactory $runtime,
    ) {
        parent::__construct($contexts, $executionContext, $pdo);
    }

    protected function configure(): void
    {
        $this->setName('ops-module:task')
            ->addArgument('action', Argument::REQUIRED, 'claim, advance, execute, heartbeat, succeed, or fail')
            ->addOption('task-key', null, Option::VALUE_OPTIONAL, 'Opaque task key')
            ->addOption('revision', null, Option::VALUE_OPTIONAL, 'Claimed execution revision')
            ->addOption('error-code', null, Option::VALUE_OPTIONAL, 'Stable allowlisted failure code')
            ->setDescription('Advance one deployment-owned Module package operation');
    }

    protected function handle(Input $input, Output $output): int
    {
        try {
            $service = $this->runtime->moduleTaskExecution();
            $action = trim((string)$input->getArgument('action'));
            $result = match ($action) {
                'claim' => $service->claim(),
                'advance' => $service->advance($this->taskKey($input), $this->revision($input)),
                'execute' => $service->execute($this->taskKey($input), $this->revision($input)),
                'heartbeat' => $service->heartbeat($this->taskKey($input), $this->revision($input)),
                'succeed' => $service->succeed($this->taskKey($input), $this->revision($input)),
                'fail' => $service->fail(
                    $this->taskKey($input),
                    $this->revision($input),
                    trim((string)$input->getOption('error-code')),
                ),
                default => throw new \RuntimeException('OPS_MODULE_TASK_ACTION_INVALID'),
            };
            $output->writeln(json_encode(['ok' => true, 'result' => $result], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
            return 0;
        } catch (Throwable $exception) {
            $code = preg_match('/^(OPS|PLUGIN|MODULE|PACKAGE)_[A-Z0-9_]+$/D', $exception->getMessage()) === 1
                ? $exception->getMessage() : 'OPS_MODULE_WORKER_FAILED';
            $output->writeln(json_encode(['ok' => false, 'error_code' => $code], JSON_THROW_ON_ERROR));
            return 1;
        }
    }

    private function taskKey(Input $input): string
    {
        $key = trim((string)$input->getOption('task-key'));
        if (preg_match('/^job_[a-f0-9]{32}$/D', $key) !== 1) throw new \RuntimeException('OPS_MODULE_TASK_KEY_INVALID');
        return $key;
    }

    private function revision(Input $input): int
    {
        $revision = trim((string)$input->getOption('revision'));
        if (preg_match('/^[1-9][0-9]*$/D', $revision) !== 1) throw new \RuntimeException('OPS_MODULE_EXECUTION_REVISION_INVALID');
        return (int)$revision;
    }
}
