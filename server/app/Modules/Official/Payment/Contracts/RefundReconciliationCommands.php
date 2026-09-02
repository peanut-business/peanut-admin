<?php
declare(strict_types=1);

namespace app\Modules\Official\Payment\Contracts;

interface RefundReconciliationCommands
{
    /** @param array<string,mixed> $diagnostics @return array{checked:int,settled:int} */
    public function reconcile(object $scope, array $diagnostics): array;
}
