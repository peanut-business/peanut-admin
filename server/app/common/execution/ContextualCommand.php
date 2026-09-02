<?php
declare(strict_types=1);

namespace app\common\execution;

use think\console\Command;
use think\console\Input;
use think\console\Output;

/** Establishes one immutable execution context around every top-level CLI command. */
abstract class ContextualCommand extends Command
{
    public function __construct(
        private readonly ExecutionContextStore $contexts,
        private readonly CurrentExecutionContext $executionContext,
    ) {
        parent::__construct();
    }

    final protected function execute(Input $input, Output $output): int
    {
        if ($this->contexts->current() !== null) {
            return $this->handle($input, $output);
        }

        try {
            return $this->contexts->run(
                new InstanceExecutionContext(
                    'console.' . $this->getName(),
                    'cli-' . getmypid() . '-' . bin2hex(random_bytes(8)),
                ),
                fn(): int => $this->handle($input, $output),
            );
        } finally {
            if (!$this->contexts->isEmpty()) {
                error_log('[ContextualCommand] execution context stack not empty after ' . $this->getName());
            }
        }
    }

    final protected function executionContext(): CurrentExecutionContext
    {
        return $this->executionContext;
    }

    abstract protected function handle(Input $input, Output $output): int;
}
