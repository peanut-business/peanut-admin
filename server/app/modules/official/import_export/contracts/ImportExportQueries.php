<?php
declare(strict_types=1);

namespace app\modules\official\import_export\contracts;

use app\modules\official\import_export\contracts\dto\AsyncExportOperation;
use PeanutAdmin\Kernel\Context\AuthorizedOperationContext;

interface ImportExportQueries
{
    /** Returns Tenant-authorized asynchronous CSV operation status only. */
    public function operation(AuthorizedOperationContext $context, string $operationKey): AsyncExportOperation;

    /** Returns the active Tenant-owned operation for a private result file. */
    public function resultFile(AuthorizedOperationContext $context, string $fileKey): AsyncExportOperation;
}
