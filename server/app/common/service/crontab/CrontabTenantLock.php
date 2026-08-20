<?php
declare(strict_types=1);

namespace app\common\service\crontab;

use PeanutAdmin\Kernel\Tenancy\PdoTenantLockStore;
use PeanutAdmin\Kernel\Tenancy\TenantScope;
use think\facade\Db;

final class CrontabTenantLock
{
    public static function name(TenantScope $scope, int $jobId): string
    {
        if ($jobId < 1) {
            throw new \InvalidArgumentException('Scheduled job ID is invalid');
        }
        return \PeanutAdmin\Kernel\Tenancy\TenantNamespace::lockName($scope, 'crontab:job:' . $jobId);
    }

    public static function acquire(TenantScope $scope, int $jobId): bool
    {
        $pdo = Db::connect()->connect();
        if (!$pdo instanceof \PDO) {
            throw new \RuntimeException('Crontab database is unavailable');
        }

        return (new PdoTenantLockStore($pdo))->acquire($scope, 'crontab:job:' . $jobId);
    }

    public static function release(TenantScope $scope, int $jobId): void
    {
        try {
            $pdo = Db::connect()->connect();
            if ($pdo instanceof \PDO) {
                (new PdoTenantLockStore($pdo))->release($scope, 'crontab:job:' . $jobId);
            }
        } catch (\Throwable) {
        }
    }
}
