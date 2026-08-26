<?php
declare(strict_types=1);

namespace app\common\service\idempotency;

use PDO;
use app\common\contract\idempotency\IdempotentCommandExecutor;

final class IdempotencyRuntimeFactory
{
    public static function forPdo(PDO $pdo): IdempotentCommandExecutor
    {
        return new PdoIdempotentCommandExecutor($pdo);
    }

    private function __construct() {}
}
