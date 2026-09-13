<?php
declare(strict_types=1);

namespace app\common\execution;

use PDO;
use app\platform\service\plugin\ModuleCatalogApplier;

/** Adds the application database only for commands that actually use it. */
abstract class DatabaseContextualCommand extends ContextualCommand
{
    public function __construct(
        ExecutionContextStore $contexts,
        CurrentExecutionContext $executionContext,
        private readonly PDO $pdo,
        private readonly ModuleCatalogApplier $catalogs,
    ) {
        parent::__construct($contexts, $executionContext);
    }

    final protected function database(): PDO
    {
        return $this->pdo;
    }

    final protected function moduleCatalogs(): ModuleCatalogApplier
    {
        return $this->catalogs;
    }
}
