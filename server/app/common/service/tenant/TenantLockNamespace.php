<?php
declare(strict_types=1);

namespace app\common\service\tenant;

/** Tenant-scoped lock naming port for Redis or advisory-lock adapters. */
final class TenantLockNamespace
{
    public function __construct(private readonly TenantScope $scope)
    {
    }

    public function name(string $logicalSeed): string
    {
        return TenantNamespace::lockName($this->scope, $logicalSeed);
    }
}
