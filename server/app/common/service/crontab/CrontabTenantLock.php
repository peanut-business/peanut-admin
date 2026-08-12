<?php
declare(strict_types=1);

namespace app\common\service\crontab;

use app\common\service\tenant\TenantLockNamespace;
use app\common\service\tenant\TenantScope;
use think\facade\Db;

final class CrontabTenantLock
{
    public static function name(TenantScope $scope, int $jobId): string
    {
        if ($jobId < 1) {
            throw new \InvalidArgumentException('Scheduled job ID is invalid');
        }
        return (new TenantLockNamespace($scope))->name('crontab:job:' . $jobId);
    }

    public static function acquire(TenantScope $scope, int $jobId): bool
    {
        $rows = Db::query(
            'SELECT GET_LOCK(:lock_name, 0) AS acquired',
            ['lock_name' => self::name($scope, $jobId)]
        );
        return (int)($rows[0]['acquired'] ?? 0) === 1;
    }

    public static function release(TenantScope $scope, int $jobId): void
    {
        try {
            Db::query(
                'SELECT RELEASE_LOCK(:lock_name)',
                ['lock_name' => self::name($scope, $jobId)]
            );
        } catch (\Throwable) {
        }
    }
}
