<?php
declare(strict_types=1);

namespace app\common\execution;

use app\platform\infrastructure\plugin\ModuleCatalogApplier;

/** Supplies Module catalog coordination without leaking a database connection into commands. */
abstract class ModuleContextualCommand extends ContextualCommand
{
    public function __construct(
        ?ExecutionContextStore $contexts = null,
        ?CurrentExecutionContext $executionContext = null,
        private readonly ?ModuleCatalogApplier $catalogs = null,
    ) {
        parent::__construct($contexts, $executionContext);
    }

    final protected function moduleCatalogs(): ModuleCatalogApplier
    {
        return $this->catalogs
            ?? throw new \LogicException('COMMAND_DEPENDENCIES_NOT_INJECTED');
    }
}
