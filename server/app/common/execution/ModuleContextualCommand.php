<?php
declare(strict_types=1);

namespace app\common\execution;

use app\platform\service\plugin\ModuleCatalogApplier;

/** Supplies Module catalog coordination without leaking a database connection into commands. */
abstract class ModuleContextualCommand extends ContextualCommand
{
    public function __construct(
        ExecutionContextStore $contexts,
        CurrentExecutionContext $executionContext,
        private readonly ModuleCatalogApplier $catalogs,
    ) {
        parent::__construct($contexts, $executionContext);
    }

    final protected function moduleCatalogs(): ModuleCatalogApplier
    {
        return $this->catalogs;
    }
}
