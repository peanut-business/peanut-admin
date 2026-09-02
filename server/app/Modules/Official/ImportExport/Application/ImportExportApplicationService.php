<?php
declare(strict_types=1);

namespace app\Modules\Official\ImportExport\Application;

use app\Modules\Official\ImportExport\Contracts\Dto\AsyncExportOperation;
use app\Modules\Official\ImportExport\Contracts\Dto\CsvExportOperation;
use app\Modules\Official\ImportExport\Contracts\ImportExportCommands;
use app\Modules\Official\ImportExport\Contracts\ImportExportQueries;
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

    private function toOperation(object $operation): AsyncExportOperation
    {
        /** @var array<string,mixed> $payload */
        $payload = $operation->toPublicArray();
        return AsyncExportOperation::fromPublicArray($payload);
    }
}
