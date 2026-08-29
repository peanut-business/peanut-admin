<?php
declare(strict_types=1);

namespace app\common\persistence;

use think\facade\Db;

/** Executes one callback while holding a bounded MySQL advisory lock. */
final class AdvisoryLockExecution
{
    public function run(string $name, int $timeoutSeconds, callable $operation): mixed
    {
        if ($name === '' || strlen($name) > 64 || $timeoutSeconds < 0 || $timeoutSeconds > 30) {
            throw new \InvalidArgumentException('DATABASE_ADVISORY_LOCK_INVALID');
        }

        $rows = Db::query(
            sprintf('SELECT GET_LOCK(:name, %d) AS acquired', $timeoutSeconds),
            ['name' => $name],
        );
        if ((int)($rows[0]['acquired'] ?? 0) !== 1) {
            throw new AdvisoryLockUnavailable('DATABASE_ADVISORY_LOCK_UNAVAILABLE');
        }

        try {
            return $operation();
        } finally {
            try {
                Db::query('SELECT RELEASE_LOCK(:name)', ['name' => $name]);
            } catch (\Throwable) {
            }
        }
    }
}
