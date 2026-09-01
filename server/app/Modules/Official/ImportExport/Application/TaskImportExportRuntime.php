<?php
declare(strict_types=1);

namespace app\Modules\Official\ImportExport\Application;

use app\Modules\Official\ImportExport\Contracts\ImportExportCommands;
use app\Modules\Official\ImportExport\Contracts\ImportExportQueries;
use app\Modules\Official\ImportExport\Contracts\Dto\AsyncExportOperation;
use app\Modules\Official\ImportExport\Infrastructure\File\AppFileMediaGateway;
use app\Modules\Official\Task\Contracts\TaskJobRuntime;
use app\Modules\Official\Task\Contracts\TaskWorkerDefinition;
use PeanutAdmin\ImportExport\Application\ImportExportService;
use PeanutAdmin\Kernel\Context\AuthorizedOperationContext;
use PeanutAdmin\Kernel\Context\AuthorizationDecision;

final readonly class TaskImportExportRuntime
{
    public function __construct(
        private ImportExportCommands $commands,
        private ImportExportQueries $queries,
        private TaskJobRuntime $tasks,
        private AppFileMediaGateway $files,
        private ImportExportTaskWorkerDefinition $worker,
    ) {
    }

    public function commands(): ImportExportCommands
    {
        return $this->commands;
    }

    public function queries(): ImportExportQueries
    {
        return $this->queries;
    }

    public function operation(AuthorizedOperationContext $context, string $operationKey): AsyncExportOperation
    {
        return $this->queries()->operation($this->asOperation($context, 'read'), $operationKey);
    }

    /** @return array{url:string,filename:string} */
    public function download(AuthorizedOperationContext $context, string $fileKey): array
    {
        return $this->files->authorizedDownload($this->asOperation($context, 'read'), $fileKey);
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
        return $this->worker;
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
