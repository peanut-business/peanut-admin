<?php
declare(strict_types=1);

namespace app\common\service\async;

use app\common\service\audit\AuditContractHost;
use app\Modules\Official\Task\Contracts\TaskJobRuntime;
use app\Modules\Official\Task\ModuleProvider as TaskModuleProvider;
use PDO;
use PeanutAdmin\ImportExport\Application\ImportExportService;
use PeanutAdmin\ImportExport\Application\OperationRecord;
use PeanutAdmin\ImportExport\Contract\DataProviderRegistry;
use PeanutAdmin\ImportExport\Execution\CsvOperationRunner;
use PeanutAdmin\ImportExport\Execution\ImportExportTaskHandler;
use PeanutAdmin\ImportExport\Execution\ImportExportTaskSubmissionProvider;
use PeanutAdmin\ImportExport\Persistence\PdoImportExportRepository;
use PeanutAdmin\Kernel\Context\AuthorizedOperationContext;
use app\common\service\export\AppFileMediaGateway;
use app\common\service\export\OperationLogExportProvider;

final readonly class TaskImportExportRuntime
{
    private TaskJobRuntime $tasks;

    public function __construct(
        private PDO $pdo,
        private string $signingKey,
        private string $privateRoot,
    ) {
        $this->tasks = (new TaskModuleProvider())->jobs($this->pdo, $this->signingKey);
    }

    public static function fromConfig(PDO $pdo): self
    {
        return new self(
            $pdo,
            (string)config('async.signing_key', ''),
            (string)config('async.private_storage_root', ''),
        );
    }

    public function submitOperationLogExport(
        AuthorizedOperationContext $context,
        string $idempotencyKey,
    ): OperationRecord {
        return $this->service()->submitExport(
            $context,
            OperationLogExportProvider::KEY,
            $idempotencyKey,
        );
    }

    public function operation(AuthorizedOperationContext $context, string $operationKey): OperationRecord
    {
        return $this->service()->detail($this->asOperation($context, 'read'), $operationKey);
    }

    /** @return array{path:string,filename:string} */
    public function download(AuthorizedOperationContext $context, string $fileKey): array
    {
        return $this->files()->authorizedDownload($this->asOperation($context, 'read'), $fileKey);
    }

    public function runTenant(int $tenantId, string $workerId): int
    {
        return $this->tasks->runTenant(
            $tenantId,
            $workerId,
            new ImportExportTaskWorkerDefinition(
                new ImportExportTaskHandler(new CsvOperationRunner(
                    new PdoImportExportRepository($this->pdo),
                    $this->providers(),
                    $this->files(),
                    AuditContractHost::fromPdo($this->pdo),
                )),
                new AdminAsyncAuthorization($this->pdo),
            ),
        );
    }

    private function service(): ImportExportService
    {
        return new ImportExportService(
            new PdoImportExportRepository($this->pdo),
            $this->providers(),
            $this->tasks->publisher(new ImportExportTaskSubmissionProvider()),
            $this->tasks->jobs(),
            AuditContractHost::fromPdo($this->pdo),
        );
    }

    private function providers(): DataProviderRegistry
    {
        return new DataProviderRegistry([new OperationLogExportProvider($this->pdo)]);
    }

    private function files(): AppFileMediaGateway
    {
        return new AppFileMediaGateway($this->pdo, $this->privateRoot);
    }

    private function asOperation(AuthorizedOperationContext $source, string $operation): AuthorizedOperationContext
    {
        return AuthorizedOperationContext::fromDecision(
            \PeanutAdmin\Kernel\Context\AuthorizationDecision::allow(
                $source->tenantContext,
                ImportExportService::RESOURCE_KEY,
                $operation,
                [],
                hash('sha256', $source->authorizationBasisDigest . '|async-export|' . $operation),
            )
        );
    }
}
