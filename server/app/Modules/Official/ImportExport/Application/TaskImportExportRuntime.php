<?php
declare(strict_types=1);

namespace app\Modules\Official\ImportExport\Application;

use app\common\persistence\CoreTenantRepositoryFactory;
use app\common\service\audit\AuditContractHost;
use app\Modules\Official\ImportExport\Contracts\ImportExportCommands;
use app\Modules\Official\ImportExport\Contracts\ImportExportQueries;
use app\Modules\Official\ImportExport\Contracts\Dto\AsyncExportOperation;
use app\Modules\Official\ImportExport\ModuleProvider as ImportExportModuleProvider;
use app\Modules\Official\ImportExport\Infrastructure\Authorization\AdminAsyncAuthorization;
use app\Modules\Official\ImportExport\Infrastructure\File\AppFileMediaGateway;
use app\common\service\export\OperationLogExportProvider;
use app\Modules\Official\Task\Contracts\TaskJobRuntime;
use app\Modules\Official\Task\Contracts\TaskWorkerDefinition;
use PDO;
use PeanutAdmin\ImportExport\Application\ImportExportService;
use PeanutAdmin\ImportExport\Contract\DataProviderRegistry;
use PeanutAdmin\ImportExport\Execution\CsvOperationRunner;
use PeanutAdmin\ImportExport\Execution\ImportExportTaskHandler;
use PeanutAdmin\Kernel\Context\AuthorizedOperationContext;
use PeanutAdmin\Kernel\Context\AuthorizationDecision;

final readonly class TaskImportExportRuntime
{
    private TaskJobRuntime $tasks;

    public function __construct(
        private PDO $pdo,
        TaskJobRuntime $tasks,
    ) {
        $this->tasks = $tasks;
    }

    public function commands(): ImportExportCommands
    {
        return (new ImportExportModuleProvider())->commands($this->pdo, $this->tasks);
    }

    public function queries(): ImportExportQueries
    {
        return (new ImportExportModuleProvider())->queries($this->pdo, $this->tasks);
    }

    public function operation(AuthorizedOperationContext $context, string $operationKey): AsyncExportOperation
    {
        return $this->queries()->operation($this->asOperation($context, 'read'), $operationKey);
    }

    /** @return array{url:string,filename:string} */
    public function download(AuthorizedOperationContext $context, string $fileKey): array
    {
        return $this->files()->authorizedDownload($this->asOperation($context, 'read'), $fileKey);
    }

    public function runTenant(int $tenantId, string $workerId): int
    {
        return $this->tasks->runTenant(
            $tenantId,
            $workerId,
            $this->workerDefinition(),
        );
    }

    public function workerDefinition(): TaskWorkerDefinition
    {
        return new ImportExportTaskWorkerDefinition(
            new ImportExportTaskHandler(new CsvOperationRunner(
                (new CoreTenantRepositoryFactory($this->pdo))->importExport(),
                $this->providers(),
                $this->files(),
                AuditContractHost::fromPdo($this->pdo),
            )),
            new AdminAsyncAuthorization($this->pdo),
        );
    }

    private function providers(): DataProviderRegistry
    {
        return new DataProviderRegistry([new OperationLogExportProvider($this->pdo)]);
    }

    private function files(): AppFileMediaGateway
    {
        return new AppFileMediaGateway($this->pdo);
    }

    private function asOperation(AuthorizedOperationContext $source, string $operation): AuthorizedOperationContext
    {
        return AuthorizedOperationContext::fromDecision(AuthorizationDecision::allow(
            $source->tenantContext,
            ImportExportService::RESOURCE_KEY,
            $operation,
            [],
            hash('sha256', $source->authorizationBasisDigest . '|async-export|' . $operation),
        ));
    }

}
