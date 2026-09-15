<?php
declare(strict_types=1);

namespace app\modules\official\import_export\services;

use app\modules\official\import_export\contracts\dto\AsyncExportOperation;
use app\modules\official\import_export\contracts\dto\CsvExportOperation;
use app\modules\official\import_export\contracts\ImportExportCommands;
use app\modules\official\import_export\contracts\ImportExportQueries;
use PeanutAdmin\ImportExport\Application\ImportExportService;
use PeanutAdmin\Kernel\Context\AuthorizedOperationContext;

final readonly class ImportExportApplicationService implements ImportExportCommands, ImportExportQueries
{
    public function __construct(private ImportExportService $service)
    {
    }

    public function submitCsvExport(
        AuthorizedOperationContext $context,
        CsvExportOperation $operation,
    ): AsyncExportOperation {
        return $this->toOperation($this->service->submitExport(
            $context,
            $operation->providerKey,
            $operation->idempotencyKey,
        ));
    }

    public function operation(AuthorizedOperationContext $context, string $operationKey): AsyncExportOperation
    {
        return $this->toOperation($this->service->detail($context, $operationKey));
    }

    public function resultFile(AuthorizedOperationContext $context, string $fileKey): AsyncExportOperation
    {
        return $this->toOperation($this->service->resultFile($context, $fileKey));
    }

    private function toOperation(object $operation): AsyncExportOperation
    {
        /** @var array<string,mixed> $payload */
        $payload = $operation->toPublicArray();
        return AsyncExportOperation::fromPublicArray($payload);
    }
}
