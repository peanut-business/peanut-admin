<?php
declare(strict_types=1);

namespace app\common\persistence;

use PeanutAdmin\Kernel\Persistence\TransactionManager;

/** Framework-owned transaction boundary for application use cases. */
final class TransactionalExecution
{
    public function __construct(private readonly TransactionManager $transactions)
    {
    }

    public function run(callable $operation): mixed
    {
        return $this->transactions->run($operation);
    }
}
