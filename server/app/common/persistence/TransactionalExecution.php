<?php
declare(strict_types=1);

namespace app\common\persistence;

use think\facade\Db;

/** Framework-owned transaction boundary for application use cases. */
final class TransactionalExecution
{
    public function run(callable $operation): mixed
    {
        return Db::transaction($operation);
    }
}
