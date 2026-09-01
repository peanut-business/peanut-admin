<?php
declare(strict_types=1);

namespace app\common\execution;

use PDO;

/** Adds the application database only for commands that actually use it. */
abstract class DatabaseContextualCommand extends ContextualCommand
{
    public function __construct(
        ExecutionContextStore $contexts,
        ExecutionContextAccess $contextAccess,
        private readonly PDO $pdo,
    ) {
        parent::__construct($contexts, $contextAccess);
    }

    final protected function database(): PDO
    {
        return $this->pdo;
    }
}
