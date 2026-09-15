<?php
declare(strict_types=1);

namespace app\modules\official\import_export\contracts;

use app\modules\official\import_export\contracts\dto\AsyncExportOperation;
use app\modules\official\import_export\contracts\dto\CsvExportOperation;
use PeanutAdmin\Kernel\Context\AuthorizedOperationContext;

interface ImportExportCommands
{
    /** Submits a CSV operation; it never writes or exposes a result file inline. */
    public function submitCsvExport(
        AuthorizedOperationContext $context,
        CsvExportOperation $operation,
    ): AsyncExportOperation;
}
