<?php
declare(strict_types=1);

namespace app\common\service\async;

use app\common\execution\ExecutionContext;
use app\common\execution\ExecutionContextStore;
use app\common\service\module\ModuleExecutionBoundary;
use PeanutAdmin\Kernel\Context\AuthorizedOperationContext;
use PeanutAdmin\TaskJob\Execution\JobExecution;
use PeanutAdmin\TaskJob\Execution\TaskHandler;

/** Rechecks the owning Module immediately before a background handler runs. */
final readonly class ModuleAwareTaskHandler implements TaskHandler
{
    public function __construct(
        private ModuleExecutionBoundary $modules,
        private ExecutionContextStore $executionContexts,
        private string $moduleKey,
        private TaskHandler $inner,
    ) {
        if (preg_match('/^[a-z0-9][a-z0-9._-]{1,127}$/D', trim($moduleKey)) !== 1) {
            throw new \InvalidArgumentException('MODULE_CONTEXT_INVALID');
        }
    }

    public function key(): string
    {
        return $this->inner->key();
    }

    public function handle(AuthorizedOperationContext $context, JobExecution $execution): void
    {
        $this->executionContexts->run(
            ExecutionContext::tenantAdmin($context->tenantContext, 'async.worker'),
            function () use ($context, $execution): void {
                $this->modules->assertWorker('official.task');
                $this->modules->assertWorker($this->moduleKey);
                $this->inner->handle($context, $execution);
            },
        );
    }
}
