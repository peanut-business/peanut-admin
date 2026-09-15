<?php
declare(strict_types=1);

namespace app\modules\official\payment\contracts;

interface RefundReconciliationCommands
{
    /** @param array<string,mixed> $diagnostics @return array{checked:int,settled:int} */
    public function reconcile(object $scope, array $diagnostics): array;
}
