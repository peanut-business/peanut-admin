<?php
declare(strict_types=1);

namespace app\common\service\crontab;

use app\common\service\tenant\TenantScope;

/** Process-local scope installed only around one synchronous Console::call. */
final class ScheduledTenantContext
{
    private static ?TenantScope $scope = null;

    public static function run(TenantScope $scope, callable $handler): mixed
    {
        if (self::$scope !== null) {
            throw new \LogicException('Scheduled TenantContext is already installed');
        }
        self::$scope = $scope;
        try {
            return $handler();
        } finally {
            self::$scope = null;
        }
    }

    public static function require(): TenantScope
    {
        return self::$scope ?? throw new \RuntimeException('Scheduled TenantContext is required');
    }

    public static function current(): ?TenantScope
    {
        return self::$scope;
    }
}
