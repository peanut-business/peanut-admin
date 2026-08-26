<?php
declare(strict_types=1);

namespace app\Modules\Official\ImportExport\Contracts;

use app\Modules\Official\ImportExport\Contracts\Dto\AsyncExportOperation;
use PeanutAdmin\Kernel\Context\AuthorizedOperationContext;

interface ImportExportQueries
{
    /** Returns Tenant-authorized asynchronous CSV operation status only. */
    public function operation(AuthorizedOperationContext $context, string $operationKey): AsyncExportOperation;
}
