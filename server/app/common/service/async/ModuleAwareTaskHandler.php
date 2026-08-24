<?php
declare(strict_types=1);

namespace app\common\service\async;

use app\common\service\module\ModuleExecutionContext;
use app\platform\service\module\PdoModuleGovernanceProvider;
use PeanutAdmin\Kernel\Context\AuthorizedOperationContext;
use PeanutAdmin\TaskJob\Execution\JobExecution;
use PeanutAdmin\TaskJob\Execution\TaskHandler;
use PDO;

/** Rechecks the owning Module immediately before a background handler runs. */
final readonly class ModuleAwareTaskHandler implements TaskHandler
{
    public function __construct(
        private PDO $pdo,
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
        $governance = PdoModuleGovernanceProvider::forExecution($this->pdo);
        $governance->executionGuard('official.task')->assertWorker(
            ModuleExecutionContext::admin('official.task', $context->tenantContext, 'async.worker'),
        );
        $moduleContext = ModuleExecutionContext::admin(
            $this->moduleKey,
            $context->tenantContext,
            'async.worker',
        );
        $governance->executionGuard($this->moduleKey)
            ->assertWorker($moduleContext);
        $this->inner->handle($context, $execution);
    }
}
