<?php
declare(strict_types=1);

namespace app\modules\official\import_export\contracts;

interface ImportExportWorkerRuntime
{
    public function runTenant(int $tenantId, string $workerId): int;
}
