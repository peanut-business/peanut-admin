<?php
declare(strict_types=1);

namespace app\common\service\async;

use PDO;
use PeanutAdmin\ImportExport\Application\ImportExportService;
use PeanutAdmin\ImportExport\Application\OperationRecord;
use PeanutAdmin\ImportExport\Contract\DataProviderRegistry;
use PeanutAdmin\ImportExport\Execution\CsvOperationRunner;
use PeanutAdmin\ImportExport\Execution\ImportExportTaskHandler;
use PeanutAdmin\ImportExport\Execution\ImportExportTaskSubmissionProvider;
use PeanutAdmin\ImportExport\Persistence\PdoImportExportRepository;
use PeanutAdmin\Kernel\Async\JobHandlerAdapter;
use PeanutAdmin\Kernel\Async\TrustedEnvelopeCodec;
use PeanutAdmin\Kernel\Context\AuthorizedOperationContext;
use PeanutAdmin\Kernel\Persistence\Pdo\PdoAuditRepository;
use PeanutAdmin\TaskJob\Application\TaskJobService;
use PeanutAdmin\TaskJob\Execution\LocalWorker;
use PeanutAdmin\TaskJob\Execution\TaskHandlerRegistry;
use PeanutAdmin\TaskJob\Persistence\PdoTaskJobRepository;
use PeanutAdmin\TaskJob\Submission\TaskSubmissionRegistry;
use PeanutAdmin\TaskJob\Submission\TrustedJobPublisher;
use app\common\service\export\AppFileMediaGateway;
use app\common\service\export\OperationLogExportProvider;

final readonly class TaskImportExportRuntime
{
    public function __construct(
        private PDO $pdo,
        private string $signingKey,
        private string $privateRoot,
    ) {
        if (strlen($signingKey) < 32) {
            throw new \RuntimeException('ASYNC_SIGNING_KEY_INVALID');
        }
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
        if ($tenantId < 1) {
            throw new \RuntimeException('ASYNC_TENANT_INVALID');
        }
        $worker = new LocalWorker(
            $tenantId,
            $workerId,
            new PdoTaskJobRepository($this->pdo),
            new TaskHandlerRegistry([
                new ModuleAwareTaskHandler(
                    $this->pdo,
                    'core',
                    new ImportExportTaskHandler(new CsvOperationRunner(
                        new PdoImportExportRepository($this->pdo),
                        $this->providers(),
                        $this->files(),
                        new PdoAuditRepository($this->pdo),
                    )),
                ),
            ]),
            new JobHandlerAdapter(
                new TrustedEnvelopeCodec($this->signingKey),
                new AdminAsyncAuthorization($this->pdo),
            ),
        );

        $processed = 0;
        $limit = min(1000, max(1, (int)config('async.worker_limit', 25)));
        while ($processed < $limit && $worker->runOnce() !== null) {
            ++$processed;
        }
        return $processed;
    }

    private function service(): ImportExportService
    {
        $jobs = new PdoTaskJobRepository($this->pdo);
        return new ImportExportService(
            new PdoImportExportRepository($this->pdo),
            $this->providers(),
            new TrustedJobPublisher(
                $jobs,
                new TaskSubmissionRegistry([new ImportExportTaskSubmissionProvider()]),
                new TrustedEnvelopeCodec($this->signingKey),
            ),
            new TaskJobService($jobs),
            new PdoAuditRepository($this->pdo),
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
