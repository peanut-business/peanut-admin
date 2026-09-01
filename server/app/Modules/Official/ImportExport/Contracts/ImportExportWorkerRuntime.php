<?php
declare(strict_types=1);

namespace app\Modules\Official\ImportExport\Contracts;

interface ImportExportWorkerRuntime
{
    public function runTenant(int $tenantId, string $workerId): int;
}
