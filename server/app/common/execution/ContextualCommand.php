<?php
declare(strict_types=1);

namespace app\common\execution;

use PDO;
use think\console\Command;
use think\console\Input;
use think\console\Output;

/** Establishes one immutable execution context around every top-level CLI command. */
abstract class ContextualCommand extends Command
{
    public function __construct(
        private readonly ExecutionContextStore $contexts,
        private readonly ExecutionContextAccess $contextAccess,
        private readonly PDO $pdo,
    ) {
        parent::__construct();
    }

    final protected function execute(Input $input, Output $output): int
    {
        if ($this->contexts->current() !== null) {
            return $this->handle($input, $output);
        }

        return $this->contexts->run(
            new InstanceExecutionContext(
                'console.' . $this->getName(),
                'cli-' . getmypid() . '-' . bin2hex(random_bytes(8)),
            ),
            fn(): int => $this->handle($input, $output),
        );
    }

    final protected function database(): PDO
    {
        return $this->pdo;
    }

    final protected function executionContext(): ExecutionContextAccess
    {
        return $this->contextAccess;
    }

    abstract protected function handle(Input $input, Output $output): int;
}
