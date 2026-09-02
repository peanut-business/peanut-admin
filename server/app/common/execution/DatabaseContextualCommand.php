<?php
declare(strict_types=1);

namespace app\common\execution;

use PDO;

/** Adds the application database only for commands that actually use it. */
abstract class DatabaseContextualCommand extends ContextualCommand
{
    public function __construct(
        ExecutionContextStore $contexts,
        CurrentExecutionContext $executionContext,
        private readonly PDO $pdo,
    ) {
        parent::__construct($contexts, $executionContext);
    }

    final protected function database(): PDO
    {
        return $this->pdo;
    }
}
