<?php
declare(strict_types=1);

namespace app\common\execution;

use think\console\Command;
use think\console\Input;
use think\console\Output;

/** Establishes one immutable execution context around every top-level CLI command. */
abstract class ContextualCommand extends Command
{
    final protected function execute(Input $input, Output $output): int
    {
        $contexts = app(ExecutionContextStore::class);
        if ($contexts->current() !== null) {
            return $this->handle($input, $output);
        }

        return $contexts->run(
            ExecutionContext::instance(
                'console.' . $this->getName(),
                'cli-' . getmypid() . '-' . bin2hex(random_bytes(8)),
            ),
            fn(): int => $this->handle($input, $output),
        );
    }

    abstract protected function handle(Input $input, Output $output): int;
}
