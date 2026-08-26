<?php
declare(strict_types=1);

namespace app\Modules\Official\ImportExport\Contracts;

use app\Modules\Official\ImportExport\Contracts\Dto\AsyncExportOperation;
use app\Modules\Official\ImportExport\Contracts\Dto\CsvExportOperation;
use PeanutAdmin\Kernel\Context\AuthorizedOperationContext;

interface ImportExportCommands
{
    /** Submits a CSV operation; it never writes or exposes a result file inline. */
    public function submitCsvExport(
        AuthorizedOperationContext $context,
        CsvExportOperation $operation,
    ): AsyncExportOperation;
}
