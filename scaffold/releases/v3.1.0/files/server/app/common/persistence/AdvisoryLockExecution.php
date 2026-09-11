<?php
declare(strict_types=1);

namespace app\common\persistence;

use app\common\service\runtime\RuntimeNamespace;
use PDO;

/** Executes one callback while holding a bounded MySQL advisory lock. */
final class AdvisoryLockExecution
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function run(string $name, int $timeoutSeconds, callable $operation): mixed
    {
        if ($name === '' || $timeoutSeconds < 0 || $timeoutSeconds > 30) {
            throw new \InvalidArgumentException('DATABASE_ADVISORY_LOCK_INVALID');
        }

        $name = RuntimeNamespace::fromEnvironment()->advisoryLockName($this->pdo, $name);
        $lock = $this->pdo->prepare(sprintf('SELECT GET_LOCK(?, %d)', $timeoutSeconds));
        $lock->execute([$name]);
        if ((int)$lock->fetchColumn() !== 1) {
            throw new AdvisoryLockUnavailable('DATABASE_ADVISORY_LOCK_UNAVAILABLE');
        }

        try {
            return $operation();
        } finally {
            try {
                $release = $this->pdo->prepare('SELECT RELEASE_LOCK(?)');
                $release->execute([$name]);
            } catch (\Throwable) {
            }
        }
    }
}
